import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Locales livrees (cdc i18n : seule `fr-FR` au Lot 0 ; ajouter une langue =
/// ajouter un catalogue, sans changer ce code).
const List<Locale> kSupportedLocales = [Locale('fr', 'FR')];
const Locale kDefaultLocale = Locale('fr', 'FR');

/// Locale active. Negociation §3.5 : preference utilisateur (`User.locale`) >
/// device > `fr-FR` (fallback systematique, jamais d'erreur).
class LocaleController extends Notifier<Locale> {
  @override
  Locale build() => kDefaultLocale;

  /// Applique une locale demandee (depuis `User.locale` ou le device), avec
  /// repli sur `fr-FR` si non livree.
  void setFromTag(String? tag) {
    if (tag == null || tag.isEmpty) return;
    final lang = tag.split(RegExp('[-_]')).first.toLowerCase();
    for (final locale in kSupportedLocales) {
      if (locale.languageCode == lang) {
        state = locale;
        return;
      }
    }
    state = kDefaultLocale;
  }

  /// Etiquette IETF pour l'en-tete `Accept-Language` (ex. `fr-FR`).
  String get tag => state.toLanguageTag();
}

final localeProvider = NotifierProvider<LocaleController, Locale>(
  LocaleController.new,
);
