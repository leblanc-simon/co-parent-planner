import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';
import '../../../core/session/session_scope.dart';
import '../../../core/sync/idempotency_scope.dart';
import '../../../core/sync/outbox_queued_exception.dart';
import '../../../core/sync/outbox_service.dart';
import '../../../core/sync/read_cache.dart';
import '../../../core/util/civil_date.dart';
import '../../family/application/family_context.dart';
import 'pending_events.dart';

/// Accès à l'agenda de la famille active (Lot 4 + offline Lot 8). Le scope `X-Family-Id`
/// est posé par l'intercepteur réseau. Écran de référence de l'offline riche (§2.2) :
/// lecture avec repli **cache local** cloisonné famille, création mise en **file outbox**
/// hors-ligne. Le même patron s'étend aux autres écrans (garde, échanges, enfants…).
class AgendaRepository {
  AgendaRepository(this._clients, this._cache, this._outbox);

  final ApiClients _clients;
  final ReadCache _cache;
  final OutboxService _outbox;

  /// Occurrences de la fenêtre `[from, to]` (dates civiles du fuseau famille).
  ///
  /// La fenêtre est désormais **explicite** : sans `from`/`to`, le serveur retombe
  /// sur le mois civil courant, si bien qu'un événement créé pour le mois suivant
  /// n'apparaissait jamais et que le bloc « À venir » de l'accueil se vidait en fin
  /// de mois (revue mobile 2026-08, constats C37 et C38/C45). Les paramètres
  /// existaient côté serveur mais n'étaient pas déclarés au contrat, donc absents du
  /// client généré.
  ///
  /// En ligne : réseau + mise en cache ; hors-ligne : repli sur le cache local
  /// cloisonné compte/famille (US-OFF-1). Le cache est cloisonné **par fenêtre** :
  /// deux mois différents ne s'écrasent pas l'un l'autre.
  Future<CachedResult<api.Event>> listEvents(
    String userId,
    String familyId, {
    required DateTime from,
    required DateTime to,
  }) async {
    final fromYmd = CivilDate.format(from);
    final toYmd = CivilDate.format(to);
    final result = await _cache.list<api.Event>(
      userId: userId,
      familyId: familyId,
      endpoint: 'events:$fromYmd:$toYmd',
      networkCall: () => _clients.events.apiFamiliesFamilyIdeventsGetCollection(
        familyId: familyId,
        from: fromYmd,
        to: toYmd,
      ),
      toJson: (e) => e.toJson(),
      fromJson: api.Event.fromJson,
    );

    // Effet optimiste (Lot 8 §3.1, constat C11) : les créations encore en file
    // n'existent que localement, le serveur comme le cache les ignorent. Sans
    // cette fusion, l'événement saisi hors-ligne n'apparaissait sur aucun écran.
    final pending = pendingEventsFrom(
      await _outbox.localEntries(userId),
      familyId: familyId,
      from: from,
      to: to,
    );
    if (pending.isEmpty) return result;

    return CachedResult<api.Event>(
      [...result.items, ...pending],
      stale: result.stale,
      // Une saisie locale est quelque chose à montrer : hors-ligne et sans cache,
      // l'écran doit l'afficher plutôt que l'état « données non disponibles ».
      available: true,
    );
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

  /// Supprime un événement ponctuel (CRUD d'US-AG-1 ; la suppression **par
  /// portée** d'une série est US-AG-6, non portée sur mobile).
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

/// Mois affiché par l'écran Agenda, comme premier jour du mois (date civile).
///
/// L'écran était figé sur le mois courant, sans aucun contrôle de période, alors
/// que le sélecteur de date du formulaire autorisait trois ans : un événement créé
/// hors du mois affiché disparaissait sans explication (constat C37).
final agendaMonthProvider = NotifierProvider<AgendaMonthController, DateTime>(
  AgendaMonthController.new,
);

class AgendaMonthController extends Notifier<DateTime> {
  @override
  DateTime build() {
    final now = DateTime.now();
    return DateTime(now.year, now.month);
  }

  void shift(int months) {
    state = DateTime(state.year, state.month + months);
  }

  void reset() {
    final now = DateTime.now();
    state = DateTime(now.year, now.month);
  }
}

/// Occurrences du mois affiché (recalculées à la bascule de famille ou de mois).
final familyEventsProvider = FutureProvider<CachedResult<api.Event>>((
  ref,
) async {
  final family = ref.watch(familyContextProvider);
  final userId = ref.watch(currentUserIdProvider);
  if (family == null || userId == null) {
    return const CachedResult<api.Event>.fresh([]);
  }
  final month = ref.watch(agendaMonthProvider);
  return ref
      .watch(agendaRepositoryProvider)
      .listEvents(
        userId,
        family.familyId,
        from: DateTime(month.year, month.month),
        // Jour 0 du mois suivant = dernier jour du mois affiché.
        to: DateTime(month.year, month.month + 1, 0),
      );
});

/// Fenêtre du bloc « À venir » de l'accueil : aujourd'hui → +60 jours.
///
/// L'accueil réutilisait la liste **mensuelle** de l'agenda, donc affichait
/// « aucun événement à venir » dès que le prochain événement tombait le mois
/// suivant (constats C38/C45).
final upcomingEventsProvider = FutureProvider<List<api.Event>>((ref) async {
  final family = ref.watch(familyContextProvider);
  final userId = ref.watch(currentUserIdProvider);
  if (family == null || userId == null) return const [];
  final today = DateTime.now();
  final result = await ref
      .watch(agendaRepositoryProvider)
      .listEvents(
        userId,
        family.familyId,
        from: DateTime(today.year, today.month, today.day),
        to: DateTime(today.year, today.month, today.day + 60),
      );
  final now = DateTime.now();
  // Même borne que `UpcomingEventsFinder` côté serveur : un événement **en cours**
  // (fin ≥ maintenant) reste listé, là où un `isAfter(startsAt)` strict le faisait
  // disparaître à 15:00 d'un créneau 14:00–18:00 (constat C38).
  bool stillRelevant(api.Event e) {
    final start = DateTime.tryParse(e.startsAt);
    final end = e.endsAt == null ? null : DateTime.tryParse(e.endsAt!);
    if (start == null) return false;
    return !start.isBefore(now) || (end != null && !end.isBefore(now));
  }

  final upcoming = result.items.where(stillRelevant).toList()
    ..sort((a, b) => a.startsAt.compareTo(b.startsAt));
  return upcoming.take(4).toList();
});
