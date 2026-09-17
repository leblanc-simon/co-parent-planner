/// Créneau proposé par défaut à « Échanger ce jour » (cdc §3.4) — un échange se négocie en heures,
/// pas en journées de minuit à minuit.
///
/// La règle suit ce que vit la famille : le parent qui récupère la journée **prolonge** sa garde
/// quand il avait déjà les enfants la veille (l'échange part alors de la transmission de la
/// veille), et vient les chercher **le matin même** sinon.
///
/// ```
/// A -> 25 | B -> 26, 27, 28     échange 18:00, journée à partir de 08:00
///   « le 26 pour A » : la veille est à A  ->  du 25 18:00 au 26 18:00
///   « le 28 pour A » : la veille est à B  ->  du 28 08:00 au 28 18:00
/// ```
///
/// Jumeau du `App\Service\Custody\SwapDefaults` PHP : les deux clients doivent proposer le même
/// créneau. Les jeux de cas des tests jumeaux (`SwapDefaultsTest` / `swap_defaults_test.dart`) sont
/// identiques ; les faire diverger est le seul risque de cette duplication, assumée parce que la
/// fiche mobile se construit hors ligne, sur les jours et le rythme déjà chargés.
library;

import '../../../core/util/civil_date.dart';
import 'day_segments.dart';

class SwapDefaults {
  const SwapDefaults({
    required this.startDate,
    required this.startTime,
    required this.endDate,
    required this.endTime,
  });

  final String startDate;
  final String startTime;
  final String endDate;
  final String endTime;

  /// [previousParentId] : parent à qui la **veille** est comptée. [targetParentId] : parent qui
  /// récupère la journée. [handoverTime] et [dayStartTime] viennent du rythme de la famille.
  factory SwapDefaults.forDay({
    required String dateKey,
    required String? previousParentId,
    required String targetParentId,
    required String handoverTime,
    required String dayStartTime,
  }) {
    final date = CivilDate.of(dateKey);
    final prolongs =
        previousParentId != null && previousParentId == targetParentId;

    final previousKey = date == null
        ? dateKey
        : CivilDate.format(DateTime(date.year, date.month, date.day - 1));
    final nextKey = date == null
        ? dateKey
        : CivilDate.format(DateTime(date.year, date.month, date.day + 1));

    // Fin par défaut : la transmission du jour même. Elle ne tient pas si l'échange commence après
    // (heure de début de journée postérieure à l'heure d'échange, réglage inhabituel mais permis) —
    // la garde court alors jusqu'à la transmission du lendemain.
    final endsSameDay =
        prolongs ||
        (DaySegments.minutesOf(handoverTime) ?? 0) >
            (DaySegments.minutesOf(dayStartTime) ?? 0);

    return SwapDefaults(
      startDate: prolongs ? previousKey : dateKey,
      startTime: prolongs ? handoverTime : dayStartTime,
      endDate: endsSameDay ? dateKey : nextKey,
      endTime: handoverTime,
    );
  }
}
