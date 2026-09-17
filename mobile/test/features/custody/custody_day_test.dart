import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/custody/application/day_detail.dart';
import 'package:coparentplanner/features/custody/presentation/custody_activities.dart';
import 'package:flutter_test/flutter_test.dart';

/// Fonctions pures de l'écran Garde : indexation des activités par jour couvert
/// (parité avec `GardeController::monthEvents` côté web) et recherche de la
/// transmission (jumelle de `DayDetailReader::nextHandover`).
void main() {
  group('indexEventsByCoveredDay', () {
    final monthStart = DateTime(2026, 7);
    final monthEnd = DateTime(2026, 7, 31);

    Map<String, List<api.Event>> index(List<api.Event> events) =>
        indexEventsByCoveredDay(
          events,
          monthStart: monthStart,
          monthEnd: monthEnd,
        );

    test('un événement d\'un jour ne marque que son jour', () {
      final byDate = index(const [
        api.Event(
          id: 'e1',
          title: 'Judo',
          startsAt: '2026-07-08T18:00:00+02:00',
          endsAt: '2026-07-08T19:30:00+02:00',
        ),
      ]);

      expect(byDate.keys, ['2026-07-08']);
    });

    test('un séjour de trois jours marque les trois jours', () {
      final byDate = index(const [
        api.Event(
          id: 'e1',
          title: 'Classe verte',
          startsAt: '2026-07-06T09:00:00+02:00',
          endsAt: '2026-07-08T17:00:00+02:00',
        ),
      ]);

      expect(byDate.keys.toList()..sort(), [
        '2026-07-06',
        '2026-07-07',
        '2026-07-08',
      ]);
    });

    test('une fin à minuit pile appartient au jour précédent', () {
      // Sans le recul d'un jour, un créneau 18h→00h poserait une pastille sur le
      // lendemain, qu'il ne concerne pas.
      final byDate = index(const [
        api.Event(
          id: 'e1',
          title: 'Soirée',
          startsAt: '2026-07-10T18:00:00+02:00',
          endsAt: '2026-07-11T00:00:00+02:00',
        ),
      ]);

      expect(byDate.keys, ['2026-07-10']);
    });

    test('un événement commencé avant le mois entre au 1er, sans déborder', () {
      final byDate = index(const [
        api.Event(
          id: 'e1',
          title: 'Vacances',
          allDay: true,
          startsAt: '2026-06-28T00:00:00+02:00',
          endsAt: '2026-08-15T00:00:00+02:00',
        ),
      ]);
      final keys = byDate.keys.toList()..sort();

      expect(keys.first, '2026-07-01');
      expect(keys.last, '2026-07-31');
      expect(keys.length, 31);
    });

    test('la date vient des composants textuels, pas d\'un instant UTC', () {
      // `DateTime.parse('2026-07-01T00:30:00+02:00')` rend un instant normalisé en
      // UTC, dont le jour est le 30 juin : la pastille tomberait la veille.
      final byDate = index(const [
        api.Event(
          id: 'e1',
          title: 'Nuit',
          startsAt: '2026-07-01T00:30:00+02:00',
          endsAt: '2026-07-01T02:00:00+02:00',
        ),
      ]);

      expect(byDate.keys, ['2026-07-01']);
    });

    test('un horodatage inexploitable est ignoré, jamais fatal', () {
      final byDate = index(const [api.Event(id: 'e1', startsAt: '')]);

      expect(byDate, isEmpty);
    });
  });

  group('nextHandover', () {
    const days = [
      api.CustodyDay(date: '2026-07-01', parentMembershipId: 'mA'),
      api.CustodyDay(date: '2026-07-02', parentMembershipId: 'mA'),
      api.CustodyDay(date: '2026-07-03', parentMembershipId: 'mB'),
      api.CustodyDay(date: '2026-07-04', parentMembershipId: 'mB'),
    ];

    test('rend la première tranche confiée à l\'autre parent', () {
      final handover = nextHandover(days, '2026-07-01', 'mA');

      expect(handover?.day.date, '2026-07-03');
      // Journée non découpée : la tranche commence à minuit.
      expect(handover?.segment.fromTime, '00:00');
    });

    test('lit l\'HEURE du changement sur le découpage du jour', () {
      const sliced = [
        api.CustodyDay(
          date: '2026-07-02',
          parentMembershipId: 'mA',
          segments: [
            {'from': '00:00', 'to': '18:00', 'parent': 'mA', 'source': 'base'},
            {'from': '18:00', 'to': '24:00', 'parent': 'mB', 'source': 'base'},
          ],
        ),
      ];

      expect(
        nextHandover(sliced, '2026-07-01', 'mA')?.segment.fromTime,
        '18:00',
      );
    });

    test('ignore les jours antérieurs ou égal au jour consulté', () {
      // Depuis le 3 (chez mB), le prochain changement n'est pas le 3 lui-même.
      expect(nextHandover(days, '2026-07-03', 'mB'), isNull);
    });

    test('rend null si le jour consulté n\'est pas attribué', () {
      expect(nextHandover(days, '2026-07-01', null), isNull);
    });

    test('l\'ordre d\'entrée n\'influe pas sur le résultat', () {
      final shuffled = days.reversed.toList();

      expect(
        nextHandover(shuffled, '2026-07-01', 'mA')?.day.date,
        '2026-07-03',
      );
    });
  });

  group('periodCovering', () {
    const holidays = [
      api.SchoolHoliday(
        id: 'h1',
        label: 'Été',
        startDate: '2026-07-04',
        endDate: '2026-08-31',
      ),
    ];

    test('trouve la période qui couvre le jour, bornes incluses', () {
      api.SchoolHoliday? covering(String date) => periodCovering(
        holidays,
        date,
        start: (h) => h.startDate,
        end: (h) => h.endDate,
      );

      expect(covering('2026-07-04')?.id, 'h1');
      expect(covering('2026-08-31')?.id, 'h1');
      expect(covering('2026-07-03'), isNull);
      expect(covering('2026-09-01'), isNull);
    });
  });

  group('slotOf', () {
    const pattern = api.CustodyPattern(
      parentAMembershipId: 'mA',
      parentBMembershipId: 'mB',
    );

    test('résout A/B, et null pour un tiers ou sans rythme', () {
      expect(slotOf('mA', pattern), 'A');
      expect(slotOf('mB', pattern), 'B');
      expect(slotOf('mC', pattern), isNull);
      expect(slotOf('mA', null), isNull);
      expect(slotOf(null, pattern), isNull);
    });
  });
}
