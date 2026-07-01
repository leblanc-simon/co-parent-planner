import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';
import '../../family/application/family_context.dart';

/// Accès à l'agenda de la famille active (Lot 4). Le scope `X-Family-Id` est posé
/// par l'intercepteur réseau. La fenêtre des occurrences n'est pas paramétrée ici :
/// le backend renvoie le **mois courant** par défaut. La création est limitée aux
/// événements **ponctuels** côté mobile (les séries s'affichent ; leur création et
/// l'édition par portée passent par le web — l'offline riche est consolidé au Lot 8).
class AgendaRepository {
  AgendaRepository(this._clients);

  final ApiClients _clients;

  /// Occurrences du mois courant, triées par date/heure (groupables par jour).
  Future<List<api.Event>> listEvents(String familyId) async {
    try {
      return await _clients.events.apiFamiliesFamilyIdeventsGetCollection(
        familyId: familyId,
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-AG-1 — crée un événement ponctuel.
  Future<api.Event> createEvent(
    String familyId, {
    required String title,
    required String category,
    required String responsibleMembershipId,
    required bool wholeFamily,
    required List<String> childIds,
    required bool allDay,
    required String startsAt,
    String? endsAt,
    String? location,
  }) async {
    try {
      return await _clients.events.apiFamiliesFamilyIdeventsPost(
        familyId: familyId,
        body: api.EventCreateEventRequest(
          title: title,
          category: category,
          responsibleMembershipId: responsibleMembershipId,
          wholeFamily: wholeFamily,
          childIds: childIds,
          allDay: allDay,
          startsAt: startsAt,
          endsAt: endsAt,
          location: location,
        ),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-AG-5 — supprime un événement ponctuel (l'édition/suppression par portée
  /// des séries reste web au Lot 4).
  Future<void> deleteEvent(String eventId) async {
    try {
      await _clients.events.apiEventsIdDelete(id: eventId);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

final agendaRepositoryProvider = Provider<AgendaRepository>(
  (ref) => AgendaRepository(ref.watch(apiClientsProvider)),
);

/// Occurrences de la famille active (recalculées à la bascule de famille).
final familyEventsProvider = FutureProvider<List<api.Event>>((ref) async {
  final family = ref.watch(familyContextProvider);
  if (family == null) return const [];
  return ref.watch(agendaRepositoryProvider).listEvents(family.familyId);
});
