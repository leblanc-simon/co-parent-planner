import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/util/civil_date.dart';
import '../../family/application/family_context.dart';
import '../data/custody_repository.dart';
import 'day_segments.dart';
import 'swap_defaults.dart';

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
    required this.segments,
    required this.exception,
    required this.holiday,
    required this.handoverDate,
    required this.handoverSlot,
    required this.handoverTime,
    required this.isPast,
    this.swapDefaults,
    this.swapTargetId,
  });

  /// Jour consulté, en date civile `AAAA-MM-JJ`.
  final String date;

  /// Jour matérialisé, `null` hors de la période générée.
  final api.CustodyDay? day;

  /// `'A'` / `'B'` du rythme, `null` si le jour n'est pas attribué.
  final String? slot;

  /// Tranches horaires de la journée, telles que le moteur les a matérialisées (cdc §3.4) : une
  /// seule pour une journée unie, deux ou trois quand les enfants changent de foyer en cours de
  /// route. Vide si le jour n'est pas matérialisé.
  final List<CustodySegment> segments;

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

  /// Créneau proposé par « Échanger ce jour » et parent qui récupérerait la journée. `null` quand
  /// l'échange n'a pas de sens (jour passé, pas de rythme, un seul parent connu).
  final SwapDefaults? swapDefaults;
  final String? swapTargetId;

  /// Exception **annulable** depuis la fiche : elle porte exactement ce jour-là, et
  /// c'est bien elle qui donne l'attribution courante.
  ///
  /// Ré-échanger un jour déjà échangé doit DÉFAIRE l'échange — retour au rythme
  /// habituel ou à la règle de vacances — et non empiler une seconde exception en sens
  /// inverse, que seul l'ordre d'application du moteur départagerait. Une exception
  /// couvrant plusieurs jours n'est pas concernée : la défaire ici toucherait des jours
  /// que l'utilisateur ne regarde pas (elle se retire depuis les réglages). Jumeau de
  /// `DayDetail::revertibleException()` côté serveur.
  api.CustodyException? get revertibleException {
    final e = exception;
    if (e == null || day?.source != 'exception' || isPast) return null;

    final civil = CivilDate.of(date);
    if (civil == null) return null;
    final first = CivilDate.format(
      DateTime(civil.year, civil.month, civil.day - 1),
    );
    final last = CivilDate.format(
      DateTime(civil.year, civil.month, civil.day + 1),
    );

    return e.startDate.compareTo(first) >= 0 && e.endDate.compareTo(last) <= 0
        ? e
        : null;
  }

  bool get hasHandover =>
      handoverDate != null && handoverSlot != null && handoverTime != null;

  /// La journée change de parent en cours de route : la case porte plusieurs teintes.
  bool get isShared => segments.length > 1;
}

/// `'A'` / `'B'` d'un membership relativement au rythme ; `null` s'il n'y figure pas.
String? slotOf(String? membershipId, api.CustodyPattern? pattern) {
  if (membershipId == null || pattern == null) return null;
  if (membershipId == pattern.parentAMembershipId) return 'A';
  if (membershipId == pattern.parentBMembershipId) return 'B';

  return null;
}

/// Première **tranche**, après [dateKey], confiée à un autre parent que [currentParentId].
///
/// Rend `null` si la journée consultée n'est pas attribuée (rien à quitter) ou si aucun
/// changement n'apparaît dans les jours fournis. Même règle que `DayDetailReader` côté serveur :
/// la transmission se lit sur les jours **matérialisés** et leur découpage horaire, jamais sur une
/// projection du rythme — sinon exceptions et règles de vacances la fausseraient.
({api.CustodyDay day, CustodySegment segment})? nextHandover(
  List<api.CustodyDay> days,
  String dateKey,
  String? currentParentId,
) {
  if (currentParentId == null) return null;
  final sorted = [...days]..sort((a, b) => a.date.compareTo(b.date));
  for (final day in sorted) {
    if (day.date.compareTo(dateKey) <= 0) continue;
    for (final segment in DaySegments.of(day).segments) {
      if (segment.parentId != null && segment.parentId != currentParentId) {
        return (day: day, segment: segment);
      }
    }
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
final custodyDayDetailProvider = FutureProvider.family<CustodyDayDetail, String>((
  ref,
  dateKey,
) async {
  final family = ref.watch(familyContextProvider);
  final pattern = ref.watch(familyCustodyPatternProvider).valueOrNull;
  final days = ref.watch(familyCustodyDaysProvider).valueOrNull ?? const [];
  final exceptions =
      ref.watch(familyCustodyExceptionsProvider).valueOrNull ?? const [];
  final holidays = ref.watch(familyHolidaysProvider).valueOrNull ?? const [];

  // La veille est chargée avec le mois (`custodyDaysWindow`) : elle porte le parent qui rend
  // les enfants, sans lequel un jour d'échange ne peut pas être décrit.
  final asDate = CivilDate.of(dateKey);
  final previousKey = asDate == null
      ? null
      : CivilDate.format(DateTime(asDate.year, asDate.month, asDate.day - 1));
  api.CustodyDay? day;
  api.CustodyDay? previousDay;
  for (final d in days) {
    if (d.date == dateKey) day = d;
    if (d.date == previousKey) previousDay = d;
  }
  final parentId = day?.parentMembershipId;
  // La veille sert au créneau proposé par « Échanger ce jour » : c'est elle qui décide si
  // l'échange prolonge une garde en cours ou commence le matin même (cdc §3.4).
  final previousParentId = previousDay?.parentMembershipId;

  // Le parent « courant » est celui qui termine la journée : c'est de chez lui que les enfants
  // repartiront, même si le jour est compté à l'autre.
  final segments = DaySegments.of(day).segments;
  final lastParentId = segments.isEmpty ? parentId : segments.last.parentId;
  var handover = nextHandover(days, dateKey, lastParentId);
  if (handover == null && lastParentId != null && family != null) {
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
        handover = nextHandover(ahead, dateKey, lastParentId);
      } catch (_) {
        // Hors-ligne ou refus : la fiche reste utile sans la transmission,
        // qui est alors omise (comme en bout d'horizon glissant).
      }
    }
  }

  final today = ref.watch(todayProvider);
  final isPast = dateKey.compareTo(CivilDate.format(today)) < 0;
  // Cible de l'échange : l'autre parent du rythme. Sans rythme complet, rien à proposer.
  final swapTargetId = pattern == null || parentId == null
      ? null
      : (parentId == pattern.parentAMembershipId
            ? pattern.parentBMembershipId
            : pattern.parentAMembershipId);

  return CustodyDayDetail(
    date: dateKey,
    day: day,
    slot: slotOf(parentId, pattern),
    segments: segments,
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
    handoverDate: handover?.day.date,
    handoverSlot: slotOf(handover?.segment.parentId, pattern),
    handoverTime: handover?.segment.fromTime,
    isPast: isPast,
    swapTargetId: swapTargetId,
    swapDefaults: swapTargetId == null || isPast
        ? null
        : SwapDefaults.forDay(
            dateKey: dateKey,
            previousParentId: previousParentId,
            targetParentId: swapTargetId,
            handoverTime: pattern?.handoverTime ?? '19:00',
            dayStartTime: pattern?.dayStartTime ?? '08:00',
          ),
  );
});
