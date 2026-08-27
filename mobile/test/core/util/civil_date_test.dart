import 'package:coparentplanner/core/util/civil_date.dart';
import 'package:flutter_test/flutter_test.dart';

/// L'API sérialise les horodatages avec l'offset du fuseau **famille**. En dériver
/// une date via `DateTime.parse` donne un instant UTC, et donc la veille dès que
/// l'heure murale est inférieure à l'offset — ce qui incluait **tous** les
/// événements « journée entière », sérialisés `T00:00:00+02:00`
/// (revue mobile 2026-08, constats C20/C36).
void main() {
  group('CivilDate.of — date civile du fuseau famille', () {
    test('une heure de nuit reste sur son jour civil', () {
      // DateTime.parse donnerait 2026-06-30T22:30Z, soit le 30 juin (mardi).
      final naive = DateTime.parse('2026-07-01T00:30:00+02:00');
      expect(naive.isUtc, isTrue, reason: 'le piège : parse normalise en UTC');
      expect(naive.day, 30, reason: 'la veille, en UTC');

      final civil = CivilDate.of('2026-07-01T00:30:00+02:00')!;
      expect(civil.isUtc, isFalse);
      expect(civil.day, 1);
      expect(civil.month, 7);
      expect(civil.weekday, DateTime.wednesday);
    });

    test('un événement journée entière reste sur son jour', () {
      final civil = CivilDate.of('2026-06-23T00:00:00+02:00')!;
      expect(civil.day, 23);
      expect(civil.weekday, DateTime.tuesday);
    });

    test('un offset négatif ne décale pas non plus', () {
      final civil = CivilDate.of('2026-06-23T23:30:00-05:00')!;
      expect(civil.day, 23);
    });

    test('chaîne inexploitable -> null', () {
      expect(CivilDate.of(''), isNull);
      expect(CivilDate.of('2026-06'), isNull);
    });
  });

  group('CivilDate.hm / ymd', () {
    test('extrait les composants textuels', () {
      expect(CivilDate.ymd('2026-07-01T00:30:00+02:00'), '2026-07-01');
      expect(CivilDate.hm('2026-07-01T00:30:00+02:00'), '00:30');
    });

    test('chaîne trop courte -> vide, jamais d\'exception', () {
      expect(CivilDate.ymd('2026'), '');
      expect(CivilDate.hm('2026-07-01'), '');
    });
  });

  group('CivilDate.format — paramètres ?from=/?to=', () {
    test('produit un Y-m-d zéro-padé, seul format accepté par AgendaRange', () {
      expect(CivilDate.format(DateTime(2026, 9, 1)), '2026-09-01');
      expect(CivilDate.format(DateTime(2026, 12, 31)), '2026-12-31');
      expect(CivilDate.format(DateTime(999, 1, 2)), '0999-01-02');
    });

    test('jour 0 du mois suivant = dernier jour du mois affiché', () {
      expect(CivilDate.format(DateTime(2026, 3, 0)), '2026-02-28');
      expect(CivilDate.format(DateTime(2028, 3, 0)), '2028-02-29');
    });
  });
}
