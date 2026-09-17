import '../../../api/generated/export.dart' as api;

/// Tranche d'une journée de garde : de [fromMinutes] (inclus) à [toMinutes] (exclu), minutes
/// écoulées depuis minuit local, chez un parent et pour une raison donnée.
class CustodySegment {
  const CustodySegment({
    required this.fromMinutes,
    required this.toMinutes,
    required this.parentId,
    required this.source,
  });

  final int fromMinutes;
  final int toMinutes;

  /// Membership du parent ; `null` si la tranche n'est attribuée à personne.
  final String? parentId;

  /// `base`, `holiday` ou `exception`.
  final String source;

  /// Part de la journée, entre 0 et 1 — l'unité dans laquelle la case est peinte.
  double get share => (toMinutes - fromMinutes) / DaySegments.minutesPerDay;

  String get fromTime => _format(fromMinutes);
  String get toTime => _format(toMinutes);

  static String _format(int minutes) =>
      '${(minutes ~/ 60).toString().padLeft(2, '0')}:'
      '${(minutes % 60).toString().padLeft(2, '0')}';
}

/// Découpage horaire d'une journée, **tel que le serveur l'a matérialisé** (cdc §3.4).
///
/// C'est le moteur qui connaît la convention de transmission — la journée appartient au parent du
/// jour jusqu'à l'heure d'échange, puis à celui du lendemain — et les échanges à l'heure près. Le
/// mobile ne la rejoue pas : il lit `CustodyDay.segments` et peint. Un jour sans découpage (journée
/// unie, ou ligne écrite avant que la garde ne connaisse les heures) rend une tranche unique.
class DaySegments {
  const DaySegments(this.segments);

  /// Minutes d'une journée — la totalité de la case.
  static const int minutesPerDay = 1440;

  final List<CustodySegment> segments;

  /// Tranches d'un jour matérialisé ; journée entière si l'API n'en donne pas.
  factory DaySegments.of(api.CustodyDay? day) {
    if (day == null) {
      return const DaySegments([]);
    }

    final raw = day.segments;
    final parsed = <CustodySegment>[];
    for (final row in raw ?? const <Map<String, String?>>[]) {
      final from = minutesOf(row['from']);
      final to = minutesOf(row['to']);
      if (from == null || to == null || from >= to) {
        parsed.clear();
        break; // découpage illisible : mieux vaut la journée unie qu'une case peinte au hasard
      }
      parsed.add(
        CustodySegment(
          fromMinutes: from,
          toMinutes: to,
          parentId: row['parent'],
          source: row['source'] ?? day.source,
        ),
      );
    }

    if (parsed.isEmpty) {
      return DaySegments([
        CustodySegment(
          fromMinutes: 0,
          toMinutes: minutesPerDay,
          parentId: day.parentMembershipId,
          source: day.source,
        ),
      ]);
    }

    return DaySegments(parsed);
  }

  /// La journée change de parent en cours de route : la case porte plusieurs teintes.
  bool get isShared => segments.length > 1;

  /// Minutes écoulées depuis minuit, ou `null` si [time] n'est pas un `HH:MM` valide.
  static int? minutesOf(String? time) {
    if (time == null) return null;
    final match = RegExp(r'^(\d{1,2}):(\d{2})$').firstMatch(time);
    if (match == null) return null;

    final hours = int.parse(match.group(1)!);
    final minutes = int.parse(match.group(2)!);
    if (hours > 24 || minutes > 59 || hours * 60 + minutes > minutesPerDay) {
      return null;
    }

    return hours * 60 + minutes;
  }
}
