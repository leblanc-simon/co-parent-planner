import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/session/session_scope.dart';
import '../../../core/util/civil_date.dart';
import '../../agenda/data/agenda_repository.dart';
import '../../family/application/family_context.dart';
import '../data/custody_repository.dart';

/// Activités d'agenda du mois affiché, indexées par jour **couvert**.
///
/// Indexer sur le seul jour de **début** — ce que faisait l'écran — laisse un séjour
/// de trois jours invisible sur les deux suivants : le parent qui consulte le
/// planning n'y voit aucune activité le mardi alors que l'enfant est en voyage
/// scolaire depuis lundi. Même règle que le planning web (`GardeController::monthEvents`).
///
/// Une fin posée à **minuit pile** appartient au jour précédent : sans ce recul, un
/// créneau 18h→00h poserait une pastille sur le lendemain, qu'il ne concerne pas.
///
/// Le regroupement se fait sur les composants **textuels** de l'horodatage, seule
/// lecture fidèle du jour civil de la famille ({@link CivilDate}).
Map<String, List<api.Event>> indexEventsByCoveredDay(
  Iterable<api.Event> events, {
  required DateTime monthStart,
  required DateTime monthEnd,
}) {
  final firstKey = CivilDate.format(monthStart);
  final lastKey = CivilDate.format(monthEnd);
  final byDate = <String, List<api.Event>>{};

  for (final event in events) {
    final start = CivilDate.of(event.startsAt);
    if (start == null) continue;

    final endIso = event.endsAt ?? event.startsAt;
    var end = CivilDate.of(endIso) ?? start;
    if (end.isAfter(start) && CivilDate.hm(endIso) == '00:00') {
      end = DateTime(end.year, end.month, end.day - 1);
    }

    // Événement commencé avant le mois : on entre au 1er plutôt que d'itérer sur
    // des jours hors grille (une période « journée entière » peut durer des semaines).
    var cursor = start;
    if (CivilDate.format(cursor).compareTo(firstKey) < 0) {
      cursor = monthStart;
    }
    while (!cursor.isAfter(end) &&
        CivilDate.format(cursor).compareTo(lastKey) <= 0) {
      byDate.putIfAbsent(CivilDate.format(cursor), () => []).add(event);
      cursor = DateTime(cursor.year, cursor.month, cursor.day + 1);
    }
  }

  return byDate;
}

/// Activités de la famille active sur le mois **du planning de garde**, par jour couvert.
///
/// Fenêtre propre : l'écran lisait `familyEventsProvider`, borné au mois de
/// l'**agenda** — naviguer dans le planning aurait affiché les pastilles d'un autre
/// mois. Lecture best-effort, comme l'overlay du Lot 5 : un échec d'agenda ne doit
/// pas casser le calendrier de garde.
final custodyMonthActivitiesProvider =
    FutureProvider<Map<String, List<api.Event>>>((ref) async {
      final family = ref.watch(familyContextProvider);
      final userId = ref.watch(currentUserIdProvider);
      if (family == null || userId == null || family.isIntact) return const {};
      final window = custodyWindow(ref.watch(custodyMonthProvider));
      final result = await ref
          .watch(agendaRepositoryProvider)
          .listEvents(
            userId,
            family.familyId,
            from: window.from,
            to: window.to,
          );

      return indexEventsByCoveredDay(
        result.items,
        monthStart: window.from,
        monthEnd: window.to,
      );
    });
