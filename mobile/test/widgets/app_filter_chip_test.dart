import 'package:coparentplanner/core/widgets/app_filter_chip.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers/pump.dart';

/// Cibles tactiles des filtres (agenda, échanges) : la zone réactive se limitait
/// au dessin de la pilule, ~32 pt de haut, sous les 44 pt des HIG iOS. Les taps un
/// peu hauts ou bas tombaient dans la liste et ne filtraient rien (constat C61).
void main() {
  testWidgets('la zone tapable fait 44 pt, la pilule garde son aspect', (
    tester,
  ) async {
    await pumpScreen(
      tester,
      const Scaffold(
        body: Wrap(
          children: [
            AppFilterChip(
              key: ValueKey('chip'),
              label: 'Santé',
              selected: false,
              onTap: _noop,
            ),
          ],
        ),
      ),
    );

    expect(
      tester.getSize(find.byKey(const ValueKey('chip'))).height,
      AppFilterChip.minTapTarget,
    );
    // La pastille elle-même n'a pas grandi : 7 + ~18 + 7 pt.
    expect(
      tester.getSize(find.byType(Container).first).height,
      lessThan(AppFilterChip.minTapTarget),
    );
  });

  testWidgets('un tap au bord haut de la cible filtre quand même', (
    tester,
  ) async {
    var tapped = 0;
    await pumpScreen(
      tester,
      Scaffold(
        body: Wrap(
          children: [
            AppFilterChip(
              key: const ValueKey('chip'),
              label: 'Santé',
              selected: false,
              onTap: () => tapped++,
            ),
          ],
        ),
      ),
    );

    final box = tester.getRect(find.byKey(const ValueKey('chip')));
    // 3 pt sous le bord supérieur : hors de la pilule dessinée, dans la cible.
    await tester.tapAt(Offset(box.center.dx, box.top + 3));
    await tester.pump();

    expect(tapped, 1);
  });
}

void _noop() {}
