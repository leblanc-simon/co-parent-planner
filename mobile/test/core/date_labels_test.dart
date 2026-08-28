import 'package:coparentplanner/core/util/date_labels.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

/// Le formatage des dates suit la **locale**, et non plus quatre tables de noms
/// français littérales dupliquées dans les écrans (revue mobile 2026-08, C40/C56).
///
/// L'invariant du `CLAUDE.md` est explicite : « ajouter une langue ne doit demander
/// aucun changement de code ». Ces cas l'exercent en formatant les mêmes dates dans
/// une seconde locale — ce que les tables littérales rendaient impossible.
void main() {
  setUpAll(() async {
    await initializeDateFormatting('fr');
    await initializeDateFormatting('en');
  });

  final date = DateTime(2026, 7, 6);

  test('libellés français', () {
    expect(DateLabels.monthYear('fr', date), 'juillet 2026');
    expect(DateLabels.dayOfMonth('fr', date), 'lundi 6 juillet');
    expect(DateLabels.longDate('fr', date), 'lundi 6 juillet 2026');
    expect(DateLabels.shortWeekday('fr', date), 'lun.');
    expect(DateLabels.isoDayMonthYear('fr', '2026-07-06'), '6 juil. 2026');
  });

  test('les mêmes dates suivent la locale, sans toucher au code', () {
    expect(DateLabels.monthYear('en', date), 'July 2026');
    expect(DateLabels.dayOfMonth('en', date), 'Monday, July 6');
    expect(DateLabels.shortWeekday('en', date), 'Mon');
  });

  group('valeurs ISO du contrat', () {
    test('une plage nomme les deux bornes, millésime sur la seconde', () {
      expect(
        DateLabels.isoRange('fr', '2026-07-04', '2026-08-31'),
        '4 juil. → 31 août 2026',
      );
    });

    test('une plage d\'un seul jour ne se répète pas', () {
      expect(
        DateLabels.isoRange('fr', '2026-07-04', '2026-07-04'),
        '4 juil. 2026',
      );
    });

    test('une valeur inexploitable est rendue telle quelle, jamais vide', () {
      expect(DateLabels.isoDayMonthYear('fr', 'bad'), 'bad');
      expect(DateLabels.isoRange('fr', 'bad', 'worse'), 'bad → worse');
      expect(DateLabels.isoDayOfMonth('fr', ''), '');
    });
  });
}
