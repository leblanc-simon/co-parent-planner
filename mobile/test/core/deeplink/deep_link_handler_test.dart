import 'package:coparentplanner/core/config/app_config.dart';
import 'package:coparentplanner/core/deeplink/deep_link_handler.dart';
import 'package:coparentplanner/routing/routes.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

/// Routage des deep links de vérification d'e-mail et de réinitialisation
/// (décision D5) — que rien ne couvrait (revue mobile 2026-08, constat C69).
///
/// L'écran `/verify-email` n'est atteignable dans l'application QUE par ce
/// handler : une régression du `switch (uri.host)` y menait au silence, sans
/// qu'aucune porte ne s'en aperçoive.
void main() {
  late GoRouter router;

  Widget page(String label) => Scaffold(body: Text(label));

  setUp(() {
    router = GoRouter(
      initialLocation: '/',
      routes: [
        GoRoute(path: '/', builder: (_, _) => page('accueil')),
        GoRoute(
          path: Routes.verifyEmail,
          builder: (_, _) => page('verification'),
        ),
        GoRoute(
          path: Routes.resetPassword,
          builder: (_, _) => page('reinitialisation'),
        ),
      ],
    );
  });
  tearDown(() => router.dispose());

  /// Monte le routeur puis lui remet le lien : la navigation est observable sur
  /// l'URI courante.
  Future<Uri> route(WidgetTester tester, String link) async {
    await tester.pumpWidget(MaterialApp.router(routerConfig: router));
    await tester.pumpAndSettle();

    DeepLinkHandler(router).handle(Uri.parse(link));
    await tester.pumpAndSettle();

    return router.routerDelegate.currentConfiguration.uri;
  }

  testWidgets('verify-email mène à l\'écran de vérification, jeton transmis', (
    tester,
  ) async {
    final uri = await route(
      tester,
      '${AppConfig.deepLinkScheme}://verify-email?token=abc123',
    );

    expect(uri.path, Routes.verifyEmail);
    expect(uri.queryParameters['token'], 'abc123');
  });

  testWidgets('reset-password mène à l\'écran de réinitialisation', (
    tester,
  ) async {
    final uri = await route(
      tester,
      '${AppConfig.deepLinkScheme}://reset-password?token=xyz',
    );

    expect(uri.path, Routes.resetPassword);
    expect(uri.queryParameters['token'], 'xyz');
  });

  testWidgets('un jeton à caractères réservés survit au passage', (
    tester,
  ) async {
    // Concaténé brut dans l'URL, un `&` coupait la route en deux paramètres.
    final uri = await route(
      tester,
      Uri(
        scheme: AppConfig.deepLinkScheme,
        host: 'verify-email',
        queryParameters: {'token': 'a&b=c d'},
      ).toString(),
    );

    expect(uri.queryParameters['token'], 'a&b=c d');
  });

  testWidgets('un schéma étranger ne navigue nulle part', (tester) async {
    final uri = await route(tester, 'https://example.org/verify-email?token=x');

    expect(uri.path, '/');
  });

  testWidgets('un hôte inconnu ne navigue nulle part', (tester) async {
    final uri = await route(
      tester,
      '${AppConfig.deepLinkScheme}://facture?token=x',
    );

    expect(uri.path, '/');
  });

  testWidgets('un lien sans jeton reste routé, avec un jeton vide', (
    tester,
  ) async {
    // L'écran affiche alors son erreur : mieux vaut y arriver que nulle part.
    final uri = await route(
      tester,
      '${AppConfig.deepLinkScheme}://verify-email',
    );

    expect(uri.path, Routes.verifyEmail);
    expect(uri.queryParameters['token'], '');
  });
}
