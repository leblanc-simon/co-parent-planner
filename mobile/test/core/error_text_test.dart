import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/error/error_text.dart';
import 'package:coparentplanner/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// Le libellé d'erreur est résolu **dans la locale de l'écran** (revue mobile
/// 2026-08, C19) : plus aucun repli français dans une couche sans `BuildContext`,
/// et plus de `e.toString()` technique montré à l'utilisateur.
void main() {
  /// Monte un écran minimal et rend `errorText(context, error)`.
  Future<String> textFor(WidgetTester tester, Object error) async {
    late String rendered;
    await tester.pumpWidget(
      MaterialApp(
        localizationsDelegates: AppL10n.localizationsDelegates,
        supportedLocales: AppL10n.supportedLocales,
        locale: const Locale('fr', 'FR'),
        home: Builder(
          builder: (context) {
            rendered = errorText(context, error);
            return const SizedBox();
          },
        ),
      ),
    );
    await tester.pump();

    return rendered;
  }

  testWidgets('le detail du serveur prime : il dit ce qui a ete refuse', (
    tester,
  ) async {
    expect(
      await textFor(
        tester,
        ApiException(statusCode: 403, detail: "Droit 'custody:edit' requis"),
      ),
      "Droit 'custody:edit' requis",
    );
  });

  testWidgets('serveur injoignable -> message reseau du catalogue', (
    tester,
  ) async {
    final text = await textFor(
      tester,
      ApiException(statusCode: null, isConnectionFailure: true),
    );

    expect(text, 'Connexion impossible. Vérifiez votre réseau.');
  });

  testWidgets('reponse d\'erreur sans detail -> repli generique localise', (
    tester,
  ) async {
    expect(
      await textFor(tester, ApiException(statusCode: 500)),
      'Une erreur est survenue. Veuillez réessayer.',
    );
  });

  testWidgets('exception non-HTTP : jamais son toString technique', (
    tester,
  ) async {
    final text = await textFor(tester, StateError('Bad state: no element'));

    expect(text, 'Une erreur est survenue. Veuillez réessayer.');
    expect(text, isNot(contains('Bad state')));
  });
}
