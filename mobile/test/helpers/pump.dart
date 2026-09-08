import 'package:coparentplanner/core/theme/app_theme.dart';
import 'package:coparentplanner/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// Monte un ecran isole dans un MaterialApp localise (fr-FR) + ProviderScope.
Future<void> pumpScreen(
  WidgetTester tester,
  Widget child, {
  List<Override> overrides = const [],
  Size size = const Size(1080, 2400),
}) async {
  // Fenetre haute par defaut : les formulaires d'auth depassent 600px et leurs
  // boutons seraient hors-ecran (echec de hit-test) sur la taille de test par
  // defaut. `size` permet a un test de debordement de viser un gabarit reel.
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);

  await tester.pumpWidget(
    ProviderScope(
      overrides: overrides,
      child: MaterialApp(
        // Le theme reel, et non celui de Material par defaut : sans lui, les
        // ecrans etaient montes sous des regles differentes de la production,
        // et une classe entiere de defauts restait invisible. `AppTheme.light()`
        // donne par exemple aux boutons une `minimumSize` de largeur infinie —
        // un bouton pose nu dans une `Row` fait alors echouer tout le layout,
        // ecran blanc a l'appui, sans qu'aucun test ne bronche.
        theme: AppTheme.light(),
        localizationsDelegates: AppL10n.localizationsDelegates,
        supportedLocales: AppL10n.supportedLocales,
        locale: const Locale('fr', 'FR'),
        home: child,
      ),
    ),
  );
  await tester.pump();
}
