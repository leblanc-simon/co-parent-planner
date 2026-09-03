import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/util/civil_date.dart';
import '../../family/application/family_context.dart';
import '../../members/data/members_repository.dart';
import '../data/custody_repository.dart';
import '../presentation/parent_initials.dart';
import 'day_detail.dart';

/// Garde du **jour**, telle que l'accueil l'annonce (US-HOME-1, maquette U1-07).
///
/// La carte héro portait un liseré constamment terracotta — la couleur du parent A
/// selon le §8 — et le libellé générique « Ouvrir le planning ». Le parent B lisait
/// donc, toute la semaine où il avait les enfants, un signal chromatique désignant
/// l'autre parent (revue mobile 2026-08, constat C59).
class CurrentCustody {
  const CurrentCustody({
    required this.slot,
    required this.parentName,
    required this.isSelf,
    required this.handoverDate,
    required this.handoverName,
    required this.handoverTime,
  });

  /// `'A'` / `'B'` du rythme, `null` si le jour n'est pas attribué (hors horizon
  /// généré, ou rythme non configuré) : la carte reste alors neutre.
  final String? slot;

  /// Nom du parent qui a les enfants aujourd'hui ; vide tant que les membres ne
  /// sont pas chargés — l'écran retombe alors sur « Parent A »/« Parent B ».
  final String parentName;

  /// Vrai quand ce parent est le membre courant (« Les enfants sont avec vous »).
  final bool isSelf;

  final String? handoverDate;
  final String? handoverName;
  final String? handoverTime;

  bool get hasHandover =>
      handoverDate != null && handoverTime != null && handoverName != null;
}

/// Fenêtre de recherche de la prochaine transmission, identique à celle de la fiche
/// de jour : le rythme le plus long du catalogue s'étale sur 14 jours et une règle
/// de vacances peut attribuer une période entière à un seul parent.
const _lookaheadDays = 45;

/// Garde du jour de la famille active, ou `null` quand il n'y a rien à annoncer
/// (famille unie, domaine `custody` explicitement refusé, jour non matérialisé).
///
/// Volontairement **indépendant** de `familyCustodyDaysProvider`, qui suit le mois
/// affiché par l'écran Garde : naviguer dans le planning ne doit pas changer ce que
/// dit l'accueil.
final currentCustodyProvider = FutureProvider<CurrentCustody?>((ref) async {
  final family = ref.watch(familyContextProvider);
  if (family == null || family.isIntact) return null;
  // Droit explicitement refusé : inutile d'aller chercher un 403.
  if (ref.watch(custodyRightProvider) == 'none') return null;

  final today = ref.watch(todayProvider);
  final todayKey = CivilDate.format(today);
  final days = await ref
      .watch(custodyRepositoryProvider)
      .listDays(
        family.familyId,
        from: today,
        to: DateTime(today.year, today.month, today.day + _lookaheadDays),
      );

  api.CustodyDay? day;
  for (final d in days) {
    if (d.date == todayKey) {
      day = d;
      break;
    }
  }

  // Attendu, et non lu au vol : sur l'accueil, rien n'a encore chargé le rythme,
  // et un `valueOrNull` rendrait `null` — donc une carte neutre — au premier passage.
  final pattern = await ref.watch(familyCustodyPatternProvider.future);
  // Noms **best-effort**, comme la légende du planning : un échec de lecture des
  // membres prive la carte des noms, pas de sa couleur.
  try {
    await ref.watch(familyMembersProvider.future);
  } catch (_) {
    // Marqueurs dégradés : `nameForSlot` rend une chaîne vide, l'écran retombe
    // sur « Parent A »/« Parent B ».
  }
  final markers = ref.watch(parentMarkersProvider);
  final parentId = day?.parentMembershipId;
  final slot = slotOf(parentId, pattern);
  final handover = nextHandover(days, todayKey, parentId);
  final handoverSlot = slotOf(handover?.parentMembershipId, pattern);

  final members =
      ref.watch(familyMembersProvider).valueOrNull ?? const <api.Member>[];
  var isSelf = false;
  for (final m in members) {
    if (m.self && m.id == parentId) isSelf = true;
  }

  return CurrentCustody(
    slot: slot,
    parentName: slot == null ? '' : markers.nameForSlot(slot),
    isSelf: isSelf,
    handoverDate: handover?.date,
    handoverName: handoverSlot == null
        ? null
        : markers.nameForSlot(handoverSlot),
    handoverTime: pattern?.handoverTime,
  );
});
