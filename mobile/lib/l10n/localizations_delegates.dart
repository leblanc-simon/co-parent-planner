import 'package:cupertino_ui/cupertino_ui.dart'
    show GlobalCupertinoLocalizations;
import 'package:flutter_localizations/flutter_localizations.dart'
    show GlobalWidgetsLocalizations;
import 'package:material_ui/material_ui.dart'
    show GlobalMaterialLocalizations, LocalizationsDelegate;

import 'app_localizations.dart';

/// Délégués de localisation de l'application, à passer à `MaterialApp`.
///
/// Remplace `AppL10n.localizationsDelegates` : `flutter gen-l10n` y référence
/// encore les délégués Material/Cupertino de `flutter_localizations`, dépréciés
/// depuis Flutter 3.47 et dont les types diffèrent de ceux de `material_ui` /
/// `cupertino_ui` — les widgets migrés n'y trouveraient pas leurs
/// `MaterialLocalizations`. Ajouter une langue ne change rien ici (ARB seuls).
const List<LocalizationsDelegate<dynamic>> appLocalizationsDelegates =
    <LocalizationsDelegate<dynamic>>[
      AppL10n.delegate,
      GlobalMaterialLocalizations.delegate,
      GlobalCupertinoLocalizations.delegate,
      GlobalWidgetsLocalizations.delegate,
    ];
