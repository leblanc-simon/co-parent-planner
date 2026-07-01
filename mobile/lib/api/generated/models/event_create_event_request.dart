// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'event_create_event_request.g.dart';

/// Événement / occurrence d'agenda exposé (Lot 4 §6.1). `GET /families/{id}/events` renvoie les.
/// **occurrences** sur [from, to] (une par occurrence de série) ; `POST` crée un événement ou une.
/// série et renvoie l'événement maître. `startsAt`/`endsAt` sont formatés en ISO 8601 avec offset du.
/// fuseau famille. Le paramètre `overlay=` est **réservé** (Lot 5, ignoré ici).
@JsonSerializable()
class EventCreateEventRequest {
  const EventCreateEventRequest({
    required this.title,
    required this.category,
    required this.responsibleMembershipId,
    required this.startsAt,
    this.wholeFamily = false,
    this.allDay = false,
    this.childIds,
    this.location,
    this.description,
    this.endsAt,
    this.recurrence,
  });
  
  factory EventCreateEventRequest.fromJson(Map<String, Object?> json) => _$EventCreateEventRequestFromJson(json);
  
  final String? title;
  final String? category;
  final String? responsibleMembershipId;
  final List<String>? childIds;
  final bool wholeFamily;
  final String? location;
  final String? description;
  final String? startsAt;
  final String? endsAt;
  final bool allDay;

  /// {freq, until?, count?, excludeHolidays?}
  final Map<String, String?>? recurrence;

  Map<String, Object?> toJson() => _$EventCreateEventRequestToJson(this);
}
