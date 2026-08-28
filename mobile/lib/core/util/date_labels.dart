import 'package:intl/intl.dart';

import 'civil_date.dart';

/// Dates affichées, formatées par `intl` dans la locale de l'application.
///
/// Le code réimplémentait la mise en forme française à la main dans quatre écrans
/// (tableaux `['janvier', …]` littéraux) et affichait ailleurs la chaîne **ISO** du
/// contrat telle quelle — « 2026-06-23 » en titre de la fiche de jour (revue mobile
/// 2026-08, constats C31/C40/C58). Les deux violent l'invariant i18n du `CLAUDE.md`
/// (« toutes les chaînes externalisées ; ajouter une langue ne doit demander aucun
/// changement de code »).
///
/// Les symboles de date sont chargés par `GlobalMaterialLocalizations`, présent dans
/// `AppL10n.localizationsDelegates` : ces fonctions attendent donc d'être appelées
/// sous un `MaterialApp` localisé (c'est le cas de tout écran, et du harnais de test).
abstract final class DateLabels {
  /// « juillet 2026 » — titre du calendrier.
  static String monthYear(String locale, DateTime date) =>
      DateFormat.yMMMM(locale).format(date);

  /// « mardi 23 juin 2026 » — titre de la fiche de jour.
  ///
  /// La maquette écrit « mardi 23 juin » sans millésime, mais elle ne navigue pas
  /// d'un mois à l'autre : depuis que le planning le fait, l'année lève l'ambiguïté
  /// (et c'est ce qu'affiche la fiche web).
  static String longDate(String locale, DateTime date) =>
      DateFormat.yMMMMEEEEd(locale).format(date);

  /// « 23 juin » — borne d'une période (vacances, exception).
  static String dayMonth(String locale, DateTime date) =>
      DateFormat.MMMd(locale).format(date);

  /// « 23 juin 2026 » à partir d'une date ISO `AAAA-MM-JJ` du contrat ; renvoie la
  /// chaîne d'origine si elle n'est pas exploitable (jamais d'affichage vide).
  static String isoDayMonthYear(String locale, String iso) {
    final date = CivilDate.of(iso);
    return date == null ? iso : DateFormat.yMMMd(locale).format(date);
  }

  /// « 4 juil. → 31 août » — plage de dates ISO du contrat.
  static String isoRange(String locale, String startIso, String endIso) {
    final start = CivilDate.of(startIso);
    final end = CivilDate.of(endIso);
    if (start == null || end == null) return '$startIso → $endIso';
    if (startIso == endIso) return isoDayMonthYear(locale, startIso);

    return '${dayMonth(locale, start)} → ${isoDayMonthYear(locale, endIso)}';
  }
}
