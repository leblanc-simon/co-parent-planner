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
        localizationsDelegates: AppL10n.localizationsDelegates,
        supportedLocales: AppL10n.supportedLocales,
        locale: const Locale('fr', 'FR'),
        home: child,
      ),
    ),
  );
  await tester.pump();
}
