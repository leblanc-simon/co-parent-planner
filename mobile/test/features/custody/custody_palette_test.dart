import 'package:coparentplanner/features/custody/application/custody_palette.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// Catalogue des huit teintes du planning (cdc §8.2) — **jumeau de `App\Enum\CustodyPalette`**.
///
/// La table est recopiée côté Dart pour que l'écran de choix fonctionne hors-ligne et que le
/// planning se peigne au premier lancement. Le seul risque de cette duplication est la divergence :
/// ce test la verrouille, exactement comme `parent_initials_test.dart` verrouille l'autre règle
/// normative dupliquée entre les deux clients.
void main() {
  test('les huit teintes du §8.2, dans l\'ordre et avec leurs triplets', () {
    expect(CustodyPalette.tints.map((t) => t.value).toList(), [
      'terracotta',
      'teal',
      'blue',
      'purple',
      'amber',
      'pink',
      'green',
      'slate',
    ]);

    const expected = {
      'terracotta': (0xFFC2643D, 0xFFF6E7DE, 0xFFA8512B),
      'teal': (0xFF2E7D71, 0xFFDCECE8, 0xFF226B60),
      'blue': (0xFF5E7CB8, 0xFFE4EAF5, 0xFF48628F),
      'purple': (0xFF8E63A8, 0xFFEDE4F2, 0xFF6F4A86),
      'amber': (0xFFB8862F, 0xFFF6EDDA, 0xFF8E671F),
      'pink': (0xFFB85C7E, 0xFFF7E4EA, 0xFF8F4460),
      'green': (0xFF5E8C55, 0xFFE5EFE2, 0xFF456B3E),
      'slate': (0xFF5F6B7A, 0xFFE7EAEE, 0xFF47515E),
    };
    for (final tint in CustodyPalette.tints) {
      final (border, soft, ink) = expected[tint.value]!;
      expect(tint.border, Color(border), reason: tint.value);
      expect(tint.soft, Color(soft), reason: tint.value);
      expect(tint.ink, Color(ink), reason: tint.value);
    }
  });

  test(
    'chaque teinte porte un NOM : la couleur ne se choisit pas seule (§8.1)',
    () {
      for (final tint in CustodyPalette.tints) {
        expect(tint.label, isNotEmpty, reason: tint.value);
      }
    },
  );

  test('les défauts du §8 : terracotta pour A, teal pour B', () {
    expect(CustodyPalette.defaultA, 'terracotta');
    expect(CustodyPalette.defaultB, 'teal');
  });

  test(
    'une clé inconnue retombe sur le défaut, elle ne laisse pas la case blanche',
    () {
      // Cas réel : un serveur plus récent dont le catalogue s'est étoffé.
      final tint = CustodyPalette.of('chartreuse', fallback: 'teal');
      expect(tint.value, 'teal');
      expect(
        CustodyPalette.of(null, fallback: 'terracotta').value,
        'terracotta',
      );
    },
  );

  test('forSlot : tout ce qui n\'est pas B est A', () {
    final colors = CustodySlotColors(
      a: CustodyPalette.tints.first,
      b: CustodyPalette.tints[1],
    );
    expect(colors.forSlot('A').value, 'terracotta');
    expect(colors.forSlot('B').value, 'teal');
    expect(colors.forSlot(null).value, 'terracotta');
  });
}
