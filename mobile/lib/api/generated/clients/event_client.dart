// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/event.dart';
import '../models/event_create_event_request.dart';
import '../models/event_update_event_request_json_merge_patch.dart';

part 'event_client.g.dart';

@RestApi()
abstract class EventClient {
  factory EventClient(Dio dio, {String? baseUrl}) = _EventClient;

  /// Removes the Event resource.
  ///
  /// Removes the Event resource.
  ///
  /// [id] - Event identifier.
  @DELETE('/api/events/{id}')
  Future<void> apiEventsIdDelete({
    @Path('id') required String id,
  });

  /// Updates the Event resource.
  ///
  /// Updates the Event resource.
  ///
  /// [id] - Event identifier.
  @PATCH('/api/events/{id}')
  Future<Event> apiEventsIdPatch({
    @Path('id') required String id,
    @Body() required EventUpdateEventRequestJsonMergePatch body,
  });

  /// Retrieves the collection of Event resources.
  ///
  /// Retrieves the collection of Event resources.
  ///
  /// [from] - Début de la fenêtre, date locale `Y-m-d`. Défaut : premier jour du mois courant.
  ///
  /// [to] - Fin de la fenêtre (incluse), date locale `Y-m-d`. Défaut : dernier jour du mois courant. Fenêtre maximale : 420 jours.
  ///
  /// [overlay] - Fusionne les événements des familles liées activées (Lot 5), en lecture seule.
  ///
  /// [familyId] - FamilyResource identifier.
  @GET('/api/families/{familyId}/events')
  Future<List<Event>> apiFamiliesFamilyIdeventsGetCollection({
    @Path('familyId') required String familyId,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('overlay') bool? overlay,
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
