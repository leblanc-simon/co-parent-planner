import 'package:dio/dio.dart';

import 'api_routes.dart';

/// Ajoute `X-Family-Id` sur les requetes metier scopees (§3.6).
///
/// - Routes non scopees (`/api/auth/*`, `/api/me`, `/api/families`,
///   `/api/realtime/token`) : aucun header ajoute.
/// - Requetes issues de l'outbox : on utilise le `X-Family-Id` FIGE porte par
///   l'entree (`options.extra['xFamilyId']`), jamais re-scope (§4.7).
/// - Sinon : la famille active courante (lue via le getter).
class FamilyScopeInterceptor extends Interceptor {
  FamilyScopeInterceptor(this._activeFamilyId);

  final String? Function() _activeFamilyId;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    // X-Family-Id fige par une entree outbox : prioritaire, jamais re-scope.
    final frozen = options.extra['xFamilyId'];
    if (frozen is String && frozen.isNotEmpty) {
      options.headers['X-Family-Id'] = frozen;
      return handler.next(options);
    }

    if (ApiRoutes.isFamilyScoped(options.path)) {
      final id = _activeFamilyId();
      if (id != null && id.isNotEmpty) {
        options.headers['X-Family-Id'] = id;
      }
    }
    handler.next(options);
  }
}
