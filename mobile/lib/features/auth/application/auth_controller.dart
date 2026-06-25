import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/i18n/locale_controller.dart';
import '../../family/application/family_context.dart';
import '../data/auth_repository.dart';
import 'auth_state.dart';

/// Orchestre l'authentification (US-AUTH-1/2). L'etat (`AuthState`) pilote les
/// redirections du routeur ; les erreurs transitoires sont relancees vers les
/// ecrans (qui gerent leur propre loading/banniere).
class AuthController extends Notifier<AuthState> {
  @override
  AuthState build() => const AuthUnauthenticated();

  AuthRepository get _repo => ref.read(authRepositoryProvider);

  /// Au demarrage : si un access token existe, tente de restaurer la session.
  Future<void> restoreSession() async {
    if (!await _repo.hasAccessToken()) {
      state = const AuthUnauthenticated();
      return;
    }
    try {
      final user = await _repo.fetchMe();
      ref.read(localeProvider.notifier).setFromTag(user.locale);
      state = AuthAuthenticated(user);
    } on ApiException {
      // Token invalide/expire et refresh impossible -> repartir propre.
      await _repo.clearTokens();
      state = const AuthUnauthenticated();
    }
  }

  /// US-AUTH-1 : inscription -> etat `PendingVerification` (aucun token, D1).
  Future<void> register({
    required String email,
    required String password,
    String? fullName,
    String? phone,
    String? locale,
  }) async {
    await _repo.register(
      email: email,
      password: password,
      fullName: fullName,
      phone: phone,
      locale: locale,
    );
    state = AuthPendingVerification(email);
  }

  /// US-AUTH-2 sc.1 : connexion. Sur 403 e-mail non verifie (D1), bascule en
  /// `PendingVerification` (le routeur dirige vers « verifiez votre e-mail »)
  /// sans relancer. Les autres erreurs sont relancees a l'ecran.
  Future<void> login({required String email, required String password}) async {
    try {
      await _repo.login(email: email, password: password);
      final user = await _repo.fetchMe();
      ref.read(localeProvider.notifier).setFromTag(user.locale);
      state = AuthAuthenticated(user);
    } on ApiException catch (e) {
      if (e.type == ProblemType.emailNotVerified) {
        state = AuthPendingVerification(email);
        return;
      }
      rethrow;
    }
  }

  /// US-AUTH-1 sc.5 : verification d'e-mail. Aucun token delivre -> l'utilisateur
  /// est invite a se connecter.
  Future<void> verifyEmail(String token) async {
    await _repo.verifyEmail(token);
    state = const AuthUnauthenticated();
  }

  Future<void> requestPasswordReset(String email) =>
      _repo.forgotPassword(email);

  /// US-AUTH-2 sc.9 : reset effectif. Le serveur revoque tous les refresh ->
  /// re-login requis.
  Future<void> resetPassword({
    required String token,
    required String password,
  }) async {
    await _repo.resetPassword(token: token, password: password);
    state = const AuthUnauthenticated();
  }

  /// US-SET-1 : met a jour le profil (locale / nom / telephone) et rafraichit
  /// l'etat. La locale prime ensuite sur `Accept-Language` (§3.5).
  Future<void> updateProfile({
    String? locale,
    String? fullName,
    String? phone,
  }) async {
    final user = await _repo.updateProfile(
      locale: locale,
      fullName: fullName,
      phone: phone,
    );
    if (locale != null) ref.read(localeProvider.notifier).setFromTag(locale);
    state = AuthAuthenticated(user);
  }

  /// US-AUTH-2 sc.7 : deconnexion volontaire.
  Future<void> logout() async {
    await _repo.logout();
    await ref.read(familyContextProvider.notifier).clear();
    state = const AuthUnauthenticated();
  }

  /// Deconnexion forcee (session perdue : refresh revoque/expire, §3.3/§4.7).
  /// Appelee par l'AuthInterceptor ; ne doit jamais lever.
  Future<void> forceLogout() async {
    await _repo.clearTokens();
    await ref.read(familyContextProvider.notifier).clear();
    state = const AuthUnauthenticated();
  }
}

final authControllerProvider = NotifierProvider<AuthController, AuthState>(
  AuthController.new,
);
