import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';
import '../../../core/network/network_providers.dart';
import '../../family/application/family_context.dart';

/// Événement projeté d'une famille liée (overlay Lot 5, lecture seule). Ne porte que la projection
/// minimale renvoyée par le backend (§3.6) : ni enfant, ni responsable, ni description, ni lieu.
class OverlayEvent {
  const OverlayEvent({
    required this.title,
    required this.startsAt,
    required this.endsAt,
    required this.category,
    required this.linkedFamilyId,
    this.allDay = false,
  });

  factory OverlayEvent.fromJson(Map<String, dynamic> json) => OverlayEvent(
    title: (json['title'] ?? '') as String,
    startsAt: (json['startsAt'] ?? '') as String,
    endsAt: (json['endsAt'] as String?) ?? (json['startsAt'] ?? '') as String,
    category: (json['category'] ?? 'autre') as String,
    linkedFamilyId: (json['linkedFamilyId'] ?? '') as String,
    allDay: (json['allDay'] ?? false) as bool,
  );

  final String title;
  final String startsAt;
  final String endsAt;
  final String category;
  final String linkedFamilyId;
  final bool allDay;
}

/// Overlay familles liées côté mobile (Lot 5) : liens actifs, préférences (opt-in par vue), et
/// événements projetés en lecture seule. Le paramètre `overlay=` de `/events` n'étant pas exposé par
/// le client généré, la lecture overlay passe par un appel dio direct (scope `X-Family-Id` posé par
/// l'intercepteur). L'offline riche de l'overlay est consolidé au Lot 8.
class OverlayRepository {
  OverlayRepository(this._clients, this._dio);

  final ApiClients _clients;
  final Dio _dio;

  Future<List<api.FamilyLink>> activeLinks(String familyId) async {
    try {
      final links = await _clients.familyLinks
          .apiFamiliesFamilyIdlinksGetCollection(familyId: familyId);
      return links.where((l) => l.status == 'active').toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<api.OverlayPreference>> preferences(String familyId) async {
    try {
      return await _clients.overlayPreferences
          .apiFamiliesFamilyIdoverlayPreferencesGetCollection(
            familyId: familyId,
          );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> setPreference(
    String familyId, {
    required String familyLinkId,
    required String view,
    required bool enabled,
  }) async {
    try {
      await _clients.overlayPreferences
          .apiFamiliesFamilyIdoverlayPreferencesPut(
            familyId: familyId,
            body: api.OverlayPreferenceUpdateOverlayPreferenceRequest(
              familyLinkId: familyLinkId,
              view: view,
              enabled: enabled,
            ),
          );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// Événements projetés (RO) des familles liées désignées, sur le mois courant (défaut serveur).
  Future<List<OverlayEvent>> overlayEvents(
    String familyId,
    List<String> linkedFamilyIds,
  ) async {
    if (linkedFamilyIds.isEmpty) {
      return const [];
    }
    try {
      final response = await _dio.get<List<dynamic>>(
        '/api/families/$familyId/events',
        queryParameters: {'overlay': linkedFamilyIds.join(',')},
      );
      return (response.data ?? const [])
          .whereType<Map<String, dynamic>>()
          .where((m) => m['source'] == 'linked')
          .map(OverlayEvent.fromJson)
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

final overlayRepositoryProvider = Provider<OverlayRepository>(
  (ref) =>
      OverlayRepository(ref.watch(apiClientsProvider), ref.watch(dioProvider)),
);

/// Liens de famille **actifs** de la famille active.
final activeLinksProvider = FutureProvider<List<api.FamilyLink>>((ref) async {
  final family = ref.watch(familyContextProvider);
  if (family == null) {
    return const [];
  }
  return ref.watch(overlayRepositoryProvider).activeLinks(family.familyId);
});

/// Préférences overlay du membre courant.
final overlayPreferencesProvider = FutureProvider<List<api.OverlayPreference>>((
  ref,
) async {
  final family = ref.watch(familyContextProvider);
  if (family == null) {
    return const [];
  }
  return ref.watch(overlayRepositoryProvider).preferences(family.familyId);
});

/// Ids des familles liées dont l'overlay est activé pour la vue donnée (`agenda` | `garde`).
List<String> _enabledFamilyIds(
  List<api.FamilyLink> links,
  List<api.OverlayPreference> prefs,
  String view,
) {
  final enabledLinkIds = {
    for (final p in prefs)
      if (view == p.view && p.enabled) p.familyLinkId,
  };
  return links
      .where((l) => enabledLinkIds.contains(l.id))
      .map((l) => l.otherFamilyId)
      .toList();
}

/// Événements overlay (vue agenda) des liens actifs dont la préférence est activée.
final overlayEventsProvider = FutureProvider<List<OverlayEvent>>((ref) async {
  final family = ref.watch(familyContextProvider);
  if (family == null) {
    return const [];
  }
  final links = await ref.watch(activeLinksProvider.future);
  final prefs = await ref.watch(overlayPreferencesProvider.future);
  final familyIds = _enabledFamilyIds(links, prefs, 'agenda');
  return ref
      .watch(overlayRepositoryProvider)
      .overlayEvents(family.familyId, familyIds);
});

/// Événements overlay (vue garde) des liens actifs dont la préférence est activée.
final overlayGardeEventsProvider = FutureProvider<List<OverlayEvent>>((
  ref,
) async {
  final family = ref.watch(familyContextProvider);
  if (family == null) {
    return const [];
  }
  final links = await ref.watch(activeLinksProvider.future);
  final prefs = await ref.watch(overlayPreferencesProvider.future);
  final familyIds = _enabledFamilyIds(links, prefs, 'garde');
  return ref
      .watch(overlayRepositoryProvider)
      .overlayEvents(family.familyId, familyIds);
});

/// Jours (YYYY-MM-DD, fuseau famille) portant une activité de famille liée — pour les pastilles
/// du calendrier de garde. Un événement multi-jours marque chaque jour de sa plage.
final overlayGardeDatesProvider = FutureProvider<Set<String>>((ref) async {
  final events = await ref.watch(overlayGardeEventsProvider.future);
  final dates = <String>{};
  for (final e in events) {
    if (e.startsAt.length < 10) {
      continue;
    }
    final start = DateTime.parse(e.startsAt.substring(0, 10));
    final end = e.endsAt.length >= 10
        ? DateTime.parse(e.endsAt.substring(0, 10))
        : start;
    var cursor = start;
    // Garde-fou : plage bornée (≤ 90 jours) pour ne jamais boucler sur une donnée aberrante.
    for (var i = 0; i <= 90 && !cursor.isAfter(end); i++) {
      dates.add(
        '${cursor.year.toString().padLeft(4, '0')}-${cursor.month.toString().padLeft(2, '0')}-${cursor.day.toString().padLeft(2, '0')}',
      );
      cursor = DateTime(cursor.year, cursor.month, cursor.day + 1);
    }
  }
  return dates;
});
