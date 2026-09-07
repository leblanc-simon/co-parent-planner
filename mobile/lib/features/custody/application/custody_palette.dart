import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/theme/app_colors.dart';
import '../data/custody_repository.dart';

/// Une des huit teintes du planning de garde (cdc §8.2).
///
/// Chaque teinte est un triplet *bordure / fond doux / encre* dont le contraste a été mesuré pour
/// que l'initiale du parent — le marqueur non chromatique qu'impose le §8.1 — reste lisible sur le
/// fond de case. C'est pourquoi la palette est **fermée** : un sélecteur libre laisserait choisir
/// un fond sur lequel ce marqueur disparaîtrait, c'est-à-dire l'information elle-même.
class CustodyTint {
  const CustodyTint({
    required this.value,
    required this.label,
    required this.border,
    required this.soft,
    required this.ink,
  });

  /// Clé stockée côté serveur (`terracotta`, `teal`…). C'est elle qui circule, jamais la couleur.
  final String value;

  /// Libellé affiché. L'UI mobile est livrée en français seul, comme le reste (décision Lot 5) ;
  /// ces huit noms sont des noms de couleurs, ils ne varient pas avec l'écran.
  final String label;

  final Color border;
  final Color soft;
  final Color ink;
}

/// Catalogue des huit teintes, **jumeau de `App\Enum\CustodyPalette`** (backend) et de la table du
/// cdc §8.2, qui fait foi.
///
/// La table est recopiée ici plutôt que servie par l'API pour trois raisons : c'est une donnée
/// **normative de design**, pas un état de la famille ; l'écran de choix doit fonctionner
/// **hors-ligne** (exigence de l'évolution B) ; et le planning doit se peindre au tout premier
/// lancement, avant tout appel à l'API des couleurs. Le risque de cette duplication — la divergence
/// — est le même que pour `ParentInitials`, et se traite de la même façon : un test verrouille les
/// huit triplets. La réponse de l'API porte bien `border`/`soft`/`ink`, mais le mobile ne lit que
/// `palette` : deux chemins vers la même couleur masqueraient justement cette divergence.
abstract final class CustodyPalette {
  static const List<CustodyTint> tints = [
    CustodyTint(
      value: 'terracotta',
      label: 'Terracotta',
      border: Color(0xFFC2643D),
      soft: Color(0xFFF6E7DE),
      ink: Color(0xFFA8512B),
    ),
    CustodyTint(
      value: 'teal',
      label: 'Teal',
      border: Color(0xFF2E7D71),
      soft: Color(0xFFDCECE8),
      ink: Color(0xFF226B60),
    ),
    CustodyTint(
      value: 'blue',
      label: 'Bleu',
      border: Color(0xFF5E7CB8),
      soft: Color(0xFFE4EAF5),
      ink: Color(0xFF48628F),
    ),
    // Volontairement distinct du #9C6FB0 de la famille liée : deux violets identiques rendraient
    // un marqueur d'overlay indiscernable d'une case de garde.
    CustodyTint(
      value: 'purple',
      label: 'Violet',
      border: Color(0xFF8E63A8),
      soft: Color(0xFFEDE4F2),
      ink: Color(0xFF6F4A86),
    ),
    CustodyTint(
      value: 'amber',
      label: 'Ambre',
      border: Color(0xFFB8862F),
      soft: Color(0xFFF6EDDA),
      ink: Color(0xFF8E671F),
    ),
    CustodyTint(
      value: 'pink',
      label: 'Rose',
      border: Color(0xFFB85C7E),
      soft: Color(0xFFF7E4EA),
      ink: Color(0xFF8F4460),
    ),
    CustodyTint(
      value: 'green',
      label: 'Vert',
      border: Color(0xFF5E8C55),
      soft: Color(0xFFE5EFE2),
      ink: Color(0xFF456B3E),
    ),
    CustodyTint(
      value: 'slate',
      label: 'Ardoise',
      border: Color(0xFF5F6B7A),
      soft: Color(0xFFE7EAEE),
      ink: Color(0xFF47515E),
    ),
  ];

  /// Teinte par défaut du parent A (§8).
  static const String defaultA = 'terracotta';

  /// Teinte par défaut du parent B (§8).
  static const String defaultB = 'teal';

  /// Teinte de clé [value], ou celle de [fallback] si la clé est inconnue.
  ///
  /// Une clé inconnue n'est pas une erreur d'affichage : elle peut venir d'un serveur plus récent
  /// dont le catalogue s'est étoffé. Le planning se peint alors dans la teinte par défaut plutôt
  /// que de rester blanc.
  static CustodyTint of(String? value, {required String fallback}) {
    for (final tint in tints) {
      if (tint.value == value) return tint;
    }

    return tints.firstWhere((t) => t.value == fallback);
  }
}

/// Les deux teintes effectives du planning, du point de vue du membre courant.
class CustodySlotColors {
  const CustodySlotColors({required this.a, required this.b});

  final CustodyTint a;
  final CustodyTint b;

  CustodyTint forSlot(String? slot) => slot == 'B' ? b : a;
}

/// Palette effective du spectateur : sa préférence pour chaque parent du rythme, sinon les teintes
/// par défaut du §8.
///
/// Lecture **best-effort**, comme les marqueurs : un chargement en cours, un refus ou un mode avion
/// sans cache retombent sur les défauts. Le planning est alors celui d'avant la personnalisation —
/// jamais un écran incolore.
final custodyPaletteProvider = Provider<CustodySlotColors>((ref) {
  final pattern = ref
      .watch(familyCustodyPatternProvider)
      .maybeWhen(data: (p) => p, orElse: () => null);
  final preferences = ref
      .watch(custodyColorsProvider)
      .maybeWhen(data: (c) => c, orElse: () => const <api.CustodyColor>[]);

  String? chosenFor(String? membershipId) {
    if (membershipId == null) return null;
    for (final pref in preferences) {
      if (pref.targetMembershipId == membershipId) return pref.palette;
    }

    return null;
  }

  return CustodySlotColors(
    a: CustodyPalette.of(
      chosenFor(pattern?.parentAMembershipId),
      fallback: CustodyPalette.defaultA,
    ),
    b: CustodyPalette.of(
      chosenFor(pattern?.parentBMembershipId),
      fallback: CustodyPalette.defaultB,
    ),
  );
});

/// Couleur de repli quand aucun parent n'est attribué (jour hors horizon, rythme absent).
const CustodyTint custodyUnassignedTint = CustodyTint(
  value: '',
  label: '',
  border: AppColors.border,
  soft: AppColors.surface,
  ink: AppColors.muted,
);
