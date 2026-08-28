import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/util/civil_date.dart';
import '../../family/application/family_context.dart';
import '../data/custody_repository.dart';

/// Détail d'un jour de garde prêt pour le rendu (US-CUST-6) : chez quel parent,
/// d'où vient l'attribution, et **quand se fait la prochaine transmission**.
///
/// La fiche mobile n'affichait que la date ISO, « Confié à : Parent A » et la source :
/// ni le nom réel du parent — que la légende juste au-dessus affiche pourtant — ni
/// l'information de transmission exigée par US-CUST-6, qui est justement ce qu'on
/// vient y chercher (revue mobile 2026-08, constat C32). Jumeau du `DayDetail` PHP
/// servant la fiche web.
class CustodyDayDetail {
  const CustodyDayDetail({
    required this.date,
    required this.day,
    required this.slot,
    required this.exception,
    required this.holiday,
    required this.handoverDate,
    required this.handoverSlot,
    required this.handoverTime,
    required this.isPast,
  });

  /// Jour consulté, en date civile `AAAA-MM-JJ`.
  final String date;

  /// Jour matérialisé, `null` hors de la période générée.
  final api.CustodyDay? day;

  /// `'A'` / `'B'` du rythme, `null` si le jour n'est pas attribué.
  final String? slot;

  /// Exception couvrant ce jour (porte la raison saisie), même si le moteur a
  /// finalement retenu une autre source.
  final api.CustodyException? exception;

  /// Période de vacances couvrant ce jour, qu'elle porte une règle ou non.
  final api.SchoolHoliday? holiday;

  /// Date du prochain changement d'attributaire ; `null` si aucun n'est matérialisé
  /// à horizon — la phrase de transmission est alors **omise** plutôt qu'inventée.
  final String? handoverDate;
  final String? handoverSlot;

  /// Heure d'échange du rythme (`HH:MM`).
  final String? handoverTime;

  /// Jour antérieur à aujourd'hui : l'API refuse d'y créer une exception (422), donc
  /// « Échanger ce jour » est présenté désactivé plutôt que de mener à un échec.
  final bool isPast;

  bool get hasHandover =>
      handoverDate != null && handoverSlot != null && handoverTime != null;
}

/// `'A'` / `'B'` d'un membership relativement au rythme ; `null` s'il n'y figure pas.
String? slotOf(String? membershipId, api.CustodyPattern? pattern) {
  if (membershipId == null || pattern == null) return null;
  if (membershipId == pattern.parentAMembershipId) return 'A';
  if (membershipId == pattern.parentBMembershipId) return 'B';

  return null;
}

/// Premier jour, **après** [dateKey], attribué à un autre parent que [currentParentId].
///
/// Rend `null` si le jour lui-même n'est pas attribué (rien à quitter) ou si aucun
/// changement n'apparaît dans les jours fournis. Même règle que `DayDetailReader`
/// côté serveur : la transmission se lit sur les jours **matérialisés**, jamais sur
/// une projection du rythme — sinon exceptions et règles de vacances la fausseraient.
api.CustodyDay? nextHandover(
  List<api.CustodyDay> days,
  String dateKey,
  String? currentParentId,
) {
  if (currentParentId == null) return null;
  final sorted = [...days]..sort((a, b) => a.date.compareTo(b.date));
  for (final day in sorted) {
    if (day.date.compareTo(dateKey) <= 0) continue;
    final next = day.parentMembershipId;
    if (next != null && next != currentParentId) return day;
  }

  return null;
}

/// Période couvrant [dateKey] parmi des intervalles `[startDate, endDate]` inclusifs.
T? periodCovering<T>(
  List<T> periods,
  String dateKey, {
  required String Function(T) start,
  required String Function(T) end,
}) {
  for (final p in periods) {
    if (start(p).compareTo(dateKey) <= 0 && end(p).compareTo(dateKey) >= 0) {
      return p;
    }
  }

  return null;
}

/// Jour courant, en date civile locale.
///
/// Injectable pour que « ce jour est passé » — qui décide si « Échanger ce jour »
/// est offert ou inerte — soit testable sans que le test ne dépende de la date à
/// laquelle il tourne (ni ne se mette à échouer l'an prochain).
final todayProvider = Provider<DateTime>((ref) {
  final now = DateTime.now();

  return DateTime(now.year, now.month, now.day);
});

/// Fenêtre de recherche du prochain changement d'attributaire, quand le mois affiché
/// n'en contient pas. Reprend les 45 jours du serveur : le rythme le plus long du
/// catalogue s'étale sur 14 jours et une règle de vacances peut attribuer une période
/// entière à un seul parent.
const _handoverLookaheadDays = 45;

/// Détail du jour [dateKey], assemblé pour la feuille de détail.
///
/// Les jours du mois affiché, les exceptions et les vacances sont déjà chargés par
/// l'écran : seule la recherche de la transmission peut demander un aller-retour
/// supplémentaire, et uniquement lorsque le mois affiché ne contient plus aucun
/// changement d'attributaire après ce jour (typiquement en fin de mois).
final custodyDayDetailProvider =
    FutureProvider.family<CustodyDayDetail, String>((ref, dateKey) async {
      final family = ref.watch(familyContextProvider);
      final pattern = ref.watch(familyCustodyPatternProvider).valueOrNull;
      final days = ref.watch(familyCustodyDaysProvider).valueOrNull ?? const [];
      final exceptions =
          ref.watch(familyCustodyExceptionsProvider).valueOrNull ?? const [];
      final holidays =
          ref.watch(familyHolidaysProvider).valueOrNull ?? const [];

      api.CustodyDay? day;
      for (final d in days) {
        if (d.date == dateKey) {
          day = d;
          break;
        }
      }
      final parentId = day?.parentMembershipId;

      var handover = nextHandover(days, dateKey, parentId);
      if (handover == null && parentId != null && family != null) {
        final from = CivilDate.of(dateKey);
        if (from != null) {
          try {
            final ahead = await ref
                .watch(custodyRepositoryProvider)
                .listDays(
                  family.familyId,
                  from: DateTime(from.year, from.month, from.day + 1),
                  to: DateTime(
                    from.year,
                    from.month,
                    from.day + _handoverLookaheadDays,
                  ),
                );
            handover = nextHandover(ahead, dateKey, parentId);
          } catch (_) {
            // Hors-ligne ou refus : la fiche reste utile sans la transmission,
            // qui est alors omise (comme en bout d'horizon glissant).
          }
        }
      }

      final today = ref.watch(todayProvider);

      return CustodyDayDetail(
        date: dateKey,
        day: day,
        slot: slotOf(parentId, pattern),
        exception: periodCovering(
          exceptions,
          dateKey,
          start: (e) => e.startDate,
          end: (e) => e.endDate,
        ),
        holiday: periodCovering(
          holidays,
          dateKey,
          start: (h) => h.startDate,
          end: (h) => h.endDate,
        ),
        handoverDate: handover?.date,
        handoverSlot: slotOf(handover?.parentMembershipId, pattern),
        handoverTime: pattern?.handoverTime,
        isPast: dateKey.compareTo(CivilDate.format(today)) < 0,
      );
    });
