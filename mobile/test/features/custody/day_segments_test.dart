import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/custody/application/day_segments.dart';
import 'package:flutter_test/flutter_test.dart';

/// Lecture du découpage horaire servi par l'API (cdc §3.4). Le mobile ne rejoue pas la règle de
/// transmission : il lit ce que le moteur a matérialisé, et retombe sur une journée entière quand
/// il n'y a rien à lire.
void main() {
  test('journée découpée : les tranches sont lues dans l\'ordre', () {
    const day = api.CustodyDay(
      date: '2026-09-25',
      parentMembershipId: 'mA',
      source: 'base',
      segments: [
        {'from': '00:00', 'to': '18:00', 'parent': 'mA', 'source': 'base'},
        {'from': '18:00', 'to': '24:00', 'parent': 'mB', 'source': 'base'},
      ],
    );

    final segments = DaySegments.of(day);

    expect(segments.isShared, isTrue);
    expect(segments.segments.first.parentId, 'mA');
    expect(segments.segments.first.share, closeTo(18 / 24, 1e-9));
    expect(segments.segments.last.fromTime, '18:00');
    expect(segments.segments.last.toTime, '24:00');
  });

  test('journée sans découpage : une tranche, tout le jour', () {
    const day = api.CustodyDay(
      date: '2026-09-26',
      parentMembershipId: 'mB',
      source: 'holiday',
    );

    final segments = DaySegments.of(day);

    expect(segments.isShared, isFalse);
    expect(segments.segments.single.parentId, 'mB');
    expect(segments.segments.single.source, 'holiday');
    expect(segments.segments.single.share, 1);
  });

  test(
    'découpage illisible : journée entière plutôt qu\'une case au hasard',
    () {
      const day = api.CustodyDay(
        date: '2026-09-26',
        parentMembershipId: 'mB',
        source: 'base',
        segments: [
          {'from': 'midi', 'to': '24:00', 'parent': 'mB', 'source': 'base'},
        ],
      );

      expect(DaySegments.of(day).isShared, isFalse);
    },
  );

  test('jour non matérialisé : aucune tranche', () {
    expect(DaySegments.of(null).segments, isEmpty);
  });
}
