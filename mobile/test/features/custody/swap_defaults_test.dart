import 'package:coparentplanner/features/custody/application/swap_defaults.dart';
import 'package:flutter_test/flutter_test.dart';

/// Créneau proposé par « Échanger ce jour » (cdc §3.4). Jeux de cas **identiques** à ceux du
/// `SwapDefaultsTest` PHP : les deux clients doivent proposer le même créneau, et c'est la seule
/// chose que cette duplication risque de laisser diverger.
void main() {
  const a = 'aaaaaaaa-0000-4000-8000-000000000001';
  const b = 'bbbbbbbb-0000-4000-8000-000000000002';

  test(
    'la veille est déjà au parent qui récupère : la garde est prolongée',
    () {
      // « A -> 25 | B -> 26, 27, 28 », le 26 pour A.
      final defaults = SwapDefaults.forDay(
        dateKey: '2026-09-26',
        previousParentId: a,
        targetParentId: a,
        handoverTime: '18:00',
        dayStartTime: '08:00',
      );

      expect(defaults.startDate, '2026-09-25');
      expect(defaults.startTime, '18:00');
      expect(defaults.endDate, '2026-09-26');
      expect(defaults.endTime, '18:00');
    },
  );

  test('la veille est à l\'autre parent : la garde reprend le matin', () {
    // « A -> 25, 29 | B -> 26, 27, 28 », le 28 pour A.
    final defaults = SwapDefaults.forDay(
      dateKey: '2026-09-28',
      previousParentId: b,
      targetParentId: a,
      handoverTime: '18:00',
      dayStartTime: '08:00',
    );

    expect(defaults.startDate, '2026-09-28');
    expect(defaults.startTime, '08:00');
    expect(defaults.endDate, '2026-09-28');
    expect(defaults.endTime, '18:00');
  });

  test('veille inconnue (bout d\'horizon) : la garde reprend le matin', () {
    final defaults = SwapDefaults.forDay(
      dateKey: '2026-09-28',
      previousParentId: null,
      targetParentId: a,
      handoverTime: '19:00',
      dayStartTime: '08:00',
    );

    expect(defaults.startDate, '2026-09-28');
    expect(defaults.startTime, '08:00');
    expect(defaults.endTime, '19:00');
  });

  test('journée qui commence après l\'échange : la fin passe au lendemain', () {
    final defaults = SwapDefaults.forDay(
      dateKey: '2026-09-28',
      previousParentId: b,
      targetParentId: a,
      handoverTime: '07:00',
      dayStartTime: '08:00',
    );

    expect(defaults.startDate, '2026-09-28');
    expect(defaults.startTime, '08:00');
    expect(defaults.endDate, '2026-09-29');
    expect(defaults.endTime, '07:00');
  });
}
