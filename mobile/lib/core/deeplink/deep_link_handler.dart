import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:go_router/go_router.dart';

import '../../routing/routes.dart';
import '../config/app_config.dart';

/// Capte les deep links `coparentplanner://verify-email?token=...` et
/// `coparentplanner://reset-password?token=...` (D5), au demarrage a froid et
/// a chaud, et route vers l'ecran correspondant.
class DeepLinkHandler {
  DeepLinkHandler(this._router, {AppLinks? appLinks})
    : _appLinks = appLinks ?? AppLinks();

  final GoRouter _router;
  final AppLinks _appLinks;
  StreamSubscription<Uri>? _sub;

  Future<void> start() async {
    final initial = await _appLinks.getInitialLink();
    if (initial != null) handle(initial);
    _sub = _appLinks.uriLinkStream.listen(handle);
  }

  /// Expose pour les tests : route selon l'URI.
  void handle(Uri uri) {
    if (uri.scheme != AppConfig.deepLinkScheme) return;
    final token = uri.queryParameters['token'] ?? '';
    switch (uri.host) {
      case 'verify-email':
        _router.go('${Routes.verifyEmail}?token=$token');
      case 'reset-password':
        _router.go('${Routes.resetPassword}?token=$token');
    }
  }

  void dispose() => unawaited(_sub?.cancel());
}
