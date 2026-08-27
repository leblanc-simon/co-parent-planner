import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import 'package:uuid/uuid.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';
import '../../../core/session/session_scope.dart';
import '../../../core/sync/idempotency_scope.dart';
import '../../../core/sync/outbox_queued_exception.dart';
import '../../../core/sync/outbox_service.dart';
import '../../../core/sync/read_cache.dart';
import '../../family/application/family_context.dart';

/// Accès à l'agenda de la famille active (Lot 4 + offline Lot 8). Le scope `X-Family-Id`
/// est posé par l'intercepteur réseau. Écran de référence de l'offline riche (§2.2) :
/// lecture avec repli **cache local** cloisonné famille, création mise en **file outbox**
/// hors-ligne. Le même patron s'étend aux autres écrans (garde, échanges, enfants…).
class AgendaRepository {
  AgendaRepository(this._clients, this._cache, this._outbox);

  final ApiClients _clients;
  final ReadCache _cache;
  final OutboxService _outbox;

  /// Occurrences du mois courant. En ligne : réseau + mise en cache ; hors-ligne :
  /// repli sur le cache local de la famille (US-OFF-1). Liste vide si rien en cache.
  Future<List<api.Event>> listEvents(String userId, String familyId) async {
    final result = await _cache.list<api.Event>(
      userId: userId,
      familyId: familyId,
      endpoint: 'events',
      networkCall: () => _clients.events.apiFamiliesFamilyIdeventsGetCollection(
        familyId: familyId,
      ),
      toJson: (e) => e.toJson(),
      fromJson: api.Event.fromJson,
    );
    return result.items;
  }

  /// US-AG-1 — crée un événement ponctuel. Hors-ligne (§2.2, US-OFF-2) : la mutation
  /// est mise en file dans l'outbox (Idempotency-Key + X-Family-Id figés) et rejouée au
  /// retour réseau ; l'appelant reçoit [OutboxQueuedException] (succès différé).
  Future<api.Event> createEvent(
    String userId,
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
    final request = api.EventCreateEventRequest(
      title: title,
      category: category,
      responsibleMembershipId: responsibleMembershipId,
      wholeFamily: wholeFamily,
      childIds: childIds,
      allDay: allDay,
      startsAt: startsAt,
      endsAt: endsAt,
      location: location,
    );
    // La clé est tirée AVANT la tentative en ligne et réutilisée par l'entrée
    // outbox : si la requête expire après traitement serveur, le rejeu est
    // dédoublonné (§6.3) au lieu de créer un second événement (constat C13).
    final idempotencyKey = const Uuid().v4();
    try {
      return await IdempotencyScope.run(
        idempotencyKey,
        () => _clients.events.apiFamiliesFamilyIdeventsPost(
          familyId: familyId,
          body: request,
        ),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        throw ApiException.fromDio(
          e,
        ); // erreur métier en ligne → remontée normale
      }
      // Hors-ligne ou timeout : mise en file avec la MÊME Idempotency-Key.
      await _outbox.enqueue(
        userId: userId,
        method: 'POST',
        path: '/api/families/$familyId/events',
        body: request.toJson(),
        xFamilyId: familyId,
        idempotencyKey: idempotencyKey,
      );
      throw const OutboxQueuedException();
    }
  }

  /// US-AG-5 — supprime un événement ponctuel.
  Future<void> deleteEvent(String eventId) async {
    try {
      await _clients.events.apiEventsIdDelete(id: eventId);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

final agendaRepositoryProvider = Provider<AgendaRepository>(
  (ref) => AgendaRepository(
    ref.watch(apiClientsProvider),
    ref.watch(readCacheProvider),
    ref.watch(outboxServiceProvider),
  ),
);

/// Occurrences de la famille active (recalculées à la bascule de famille).
final familyEventsProvider = FutureProvider<List<api.Event>>((ref) async {
  final family = ref.watch(familyContextProvider);
  final userId = ref.watch(currentUserIdProvider);
  if (family == null || userId == null) return const [];
  return ref
      .watch(agendaRepositoryProvider)
      .listEvents(userId, family.familyId);
});
