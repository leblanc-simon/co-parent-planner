import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';
import '../../../core/storage/secure_storage.dart';
import '../../../core/storage/storage_providers.dart';
import '../application/auth_state.dart';

/// Acces aux endpoints d'auth/compte. Enveloppe les clients generes, traduit les
/// `DioException` en `ApiException` normalisees, et gere le stockage des jetons.
class AuthRepository {
  AuthRepository(this._clients, this._storage);

  final ApiClients _clients;
  final SecureStorage _storage;

  Future<bool> hasAccessToken() async =>
      (await _storage.readAccessToken())?.isNotEmpty ?? false;

  Future<void> clearTokens() => _storage.clearTokens();

  /// US-AUTH-1 : inscription. Aucun token delivre (D1) ; reponse anti-enumerante.
  Future<void> register({
    required String email,
    required String password,
    String? fullName,
    String? phone,
    String? locale,
  }) async {
    try {
      await _clients.register.apiAuthregisterPost(
        body: api.RegisterRequest(
          email: email,
          password: password,
          fullName: fullName,
          phone: phone,
          locale: locale,
        ),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-AUTH-2 sc.1 : connexion. Stocke access + refresh (rotatif).
  Future<void> login({required String email, required String password}) async {
    try {
      final res = await _clients.login.apiAuthloginPost(
        body: api.LoginRequest(email: email, password: password),
      );
      final access = res.accessToken;
      final refresh = res.refreshToken;
      if (access == null || refresh == null) {
        throw ApiException(
          statusCode: 500,
          detail: 'Reponse de connexion invalide.',
        );
      }
      await _storage.writeTokens(accessToken: access, refreshToken: refresh);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<UserProfile> fetchMe() async {
    try {
      final me = await _clients.me.apiMeGet();
      return UserProfile.fromMe(me);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// Met a jour le profil (Merge Patch). N'envoyer que les champs voulus.
  Future<UserProfile> updateProfile({
    String? locale,
    String? fullName,
    String? phone,
  }) async {
    try {
      final me = await _clients.me.apiMePatch(
        body: api.MeMeWriteJsonMergePatch(
          locale: locale,
          fullName: fullName,
          phone: phone,
        ),
      );
      return UserProfile.fromMe(me);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-AUTH-1 sc.5 : verification d'e-mail (endpoint unique, deep link D5).
  Future<void> verifyEmail(String token) async {
    try {
      await _clients.verifyEmail.apiAuthverifyEmailPost(
        body: api.VerifyEmailRequest(token: token),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-AUTH-2 sc.8 : demande de reset (reponse toujours generique).
  Future<void> forgotPassword(String email) async {
    try {
      await _clients.forgotPassword.apiAuthpasswordforgotPost(
        body: api.ForgotPasswordRequest(email: email),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-AUTH-2 sc.9 : reset effectif. Revoque cote serveur tous les refresh.
  Future<void> resetPassword({
    required String token,
    required String password,
  }) async {
    try {
      await _clients.resetPassword.apiAuthpasswordresetPost(
        body: api.ResetPasswordRequest(token: token, password: password),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-AUTH-2 sc.7 : deconnexion. Revoque le refresh courant (best-effort) puis
  /// efface le stockage local quoi qu'il arrive.
  Future<void> logout() async {
    final refreshToken = await _storage.readRefreshToken();
    try {
      await _clients.logout.apiAuthlogoutPost(
        body: api.LogoutRequest(refreshToken: refreshToken),
      );
    } on DioException {
      // Best-effort : meme si le serveur est injoignable, on purge en local.
    }
    await _storage.clearTokens();
  }
}

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(
    ref.watch(apiClientsProvider),
    ref.watch(secureStorageProvider),
  ),
);
