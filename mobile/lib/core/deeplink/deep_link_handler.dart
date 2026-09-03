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

  /// Route selon l'URI. Un schéma étranger ou un hôte inconnu ne navigue nulle
  /// part : le lien n'est pas le nôtre.
  ///
  /// Le jeton est passé par `Uri` plutôt que concaténé : le serveur l'émet déjà
  /// URL-safe, mais une interpolation brute romprait la route au premier `&` ou
  /// `#` reçu (revue mobile 2026-08, constat C69).
  void handle(Uri uri) {
    if (uri.scheme != AppConfig.deepLinkScheme) return;
    final token = uri.queryParameters['token'] ?? '';
    final path = switch (uri.host) {
      'verify-email' => Routes.verifyEmail,
      'reset-password' => Routes.resetPassword,
      _ => null,
    };
    if (path == null) return;
    _router.go(Uri(path: path, queryParameters: {'token': token}).toString());
  }

  void dispose() => unawaited(_sub?.cancel());
}
