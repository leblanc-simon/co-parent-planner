// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/event.dart';
import '../models/event_create_event_request.dart';
import '../models/event_update_event_request_json_merge_patch.dart';
import '../models/scope.dart';

part 'event_client.g.dart';

@RestApi()
abstract class EventClient {
  factory EventClient(Dio dio, {String? baseUrl}) = _EventClient;

  /// Removes the Event resource.
  ///
  /// Removes the Event resource.
  ///
  /// [id] - Event identifier.
  ///
  /// [scope] - Portée de la suppression : `occurrence` (exclusion de la seule occurrence) ou `series`. Requis pour un événement récurrent.
  ///
  /// [recurrenceDate] - Occurrence visée par `scope=occurrence` : date d'origine (AAAA-MM-JJ) ou instant ISO 8601 renvoyé par l'API.
  ///
  /// [from] - Pivot de `scope=series` : supprime cette occurrence et les suivantes. Absent = toute la série.
  @DELETE('/api/events/{id}')
  Future<void> apiEventsIdDelete({
    @Path('id') required String id,
    @Query('scope') Scope? scope,
    @Query('recurrenceDate') String? recurrenceDate,
    @Query('from') String? from,
  });

  /// Updates the Event resource.
  ///
  /// Updates the Event resource.
  ///
  /// [id] - Event identifier.
  ///
  /// [scope] - Portée de l'édition d'une série : `occurrence` (override de la seule occurrence `recurrenceDate`) ou `series` (cette occurrence et les suivantes, avec scission). Requis pour un événement récurrent, ignoré pour un ponctuel.
  @PATCH('/api/events/{id}')
  Future<Event> apiEventsIdPatch({
    @Path('id') required String id,
    @Body() required EventUpdateEventRequestJsonMergePatch body,
    @Query('scope') Scope? scope,
  });

  /// Retrieves the collection of Event resources.
  ///
  /// Retrieves the collection of Event resources.
  ///
  /// [familyId] - FamilyResource identifier.
  ///
  /// [from] - Début de la fenêtre (date locale famille, AAAA-MM-JJ). Défaut : 1er du mois courant.
  ///
  /// [to] - Fin de la fenêtre, incluse (AAAA-MM-JJ). Défaut : dernier jour du mois courant. Fenêtre maximale : 420 jours.
  ///
  /// [category] - Filtre de catégories, séparées par des virgules (ex. `school,health`). Absent = toutes.
  ///
  /// [overlay] - Identifiants des familles liées dont projeter les événements en overlay, séparés par des virgules (US-AG-4, lecture seule). Absent = aucun overlay.
  @GET('/api/families/{familyId}/events')
  Future<List<Event>> apiFamiliesFamilyIdeventsGetCollection({
    @Path('familyId') required String familyId,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('category') String? category,
    @Query('overlay') String? overlay,
  });

  /// Creates a Event resource.
  ///
  /// Creates a Event resource.
  ///
  /// [familyId] - FamilyResource identifier.
  @POST('/api/families/{familyId}/events')
  Future<Event> apiFamiliesFamilyIdeventsPost({
    @Path('familyId') required String familyId,
    @Body() required EventCreateEventRequest body,
  });
}
