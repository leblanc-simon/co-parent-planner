import 'dart:async';

import 'package:dio/dio.dart';

import '../../storage/secure_storage.dart';
import 'api_routes.dart';

/// Bearer + refresh rotatif **single-flight** (cœur de la decision D3, §3.3/§4.2).
///
/// - `onRequest` : attache `Authorization: Bearer <access>` (sauf routes
///   d'auth publiques).
/// - `onError` (401) : rafraichit le token **une seule fois en vol** puis rejoue
///   la requete. Si une requete concurrente a deja rafraichi (token en stockage
///   different de celui utilise), on rejoue directement sans nouveau refresh —
///   on ne declenche donc JAMAIS deux refresh concurrents avec le meme jeton
///   (sinon le serveur invalide toute la famille de tokens).
///
/// `QueuedInterceptor` serialise les passages : defense supplementaire contre la
/// concurrence. Le refresh et le rejeu passent par un Dio « brut » sans cet
/// intercepteur (`_rawDio`) pour eviter toute reentrance.
class AuthInterceptor extends QueuedInterceptor {
  AuthInterceptor({
    required SecureStorage storage,
    required Dio rawDio,
    required Future<void> Function() onSessionExpired,
  }) : _storage = storage,
       _rawDio = rawDio,
       _onSessionExpired = onSessionExpired;

  final SecureStorage _storage;
  final Dio _rawDio;
  final Future<void> Function() _onSessionExpired;

  Completer<String?>? _refreshInFlight;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    if (!ApiRoutes.isAuthRoute(options.path)) {
      final token = await _storage.readAccessToken();
      if (token != null && token.isNotEmpty) {
        options.headers['Authorization'] = 'Bearer $token';
      }
    }
    handler.next(options);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final options = err.requestOptions;
    final is401 = err.response?.statusCode == 401;

    if (!is401 ||
        options.path.contains(ApiRoutes.refresh) ||
        options.extra['__retried'] == true) {
      return handler.next(err);
    }

    final usedAuth = options.headers['Authorization'];

    // Un autre appel a-t-il deja rafraichi ? (token en stockage != celui utilise)
    final current = await _storage.readAccessToken();
    if (current != null &&
        current.isNotEmpty &&
        'Bearer $current' != usedAuth) {
      return _replay(options, current, handler, err);
    }

    String? newAccess;
    try {
      newAccess = await _ensureRefreshed();
    } catch (_) {
      newAccess = null;
    }

    if (newAccess == null || newAccess.isEmpty) {
      // Refresh impossible (revoque/expire) -> session perdue.
      await _onSessionExpired();
      return handler.next(err);
    }

    await _replay(options, newAccess, handler, err);
  }

  Future<void> _replay(
    RequestOptions options,
    String accessToken,
    ErrorInterceptorHandler handler,
    DioException original,
  ) async {
    options.headers['Authorization'] = 'Bearer $accessToken';
    options.extra['__retried'] = true;
    try {
      final response = await _rawDio.fetch<dynamic>(options);
      handler.resolve(response);
    } on DioException catch (e) {
      handler.next(e);
    }
  }

  /// Single-flight : un seul `POST /api/auth/refresh` en vol. Renvoie le nouvel
  /// access token, ou `null` si le refresh echoue.
  Future<String?> _ensureRefreshed() {
    final existing = _refreshInFlight;
    if (existing != null) return existing.future;

    final completer = Completer<String?>();
    _refreshInFlight = completer;

    unawaited(
      _doRefresh()
          .then(
            completer.complete,
            onError: (Object e) => completer.complete(null),
          )
          .whenComplete(() => _refreshInFlight = null),
    );

    return completer.future;
  }

  Future<String?> _doRefresh() async {
    final refreshToken = await _storage.readRefreshToken();
    if (refreshToken == null || refreshToken.isEmpty) return null;

    final response = await _rawDio.post<dynamic>(
      ApiRoutes.refresh,
      data: {'refreshToken': refreshToken},
    );
    final data = response.data;
    if (data is! Map) return null;
    final access = data['accessToken']?.toString();
    final refresh = data['refreshToken']?.toString();
    if (access == null || refresh == null) return null;

    // Rotation : on remplace les DEUX jetons immediatement (§3.3).
    await _storage.writeTokens(accessToken: access, refreshToken: refresh);
    return access;
  }
}
