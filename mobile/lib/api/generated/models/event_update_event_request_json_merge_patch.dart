// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'event_update_event_request_json_merge_patch.g.dart';

/// Événement / occurrence d'agenda exposé (Lot 4 §6.1). `GET /families/{id}/events` renvoie les.
/// **occurrences** sur [from, to] (une par occurrence de série) ; `POST` crée un événement ou une.
/// série et renvoie l'événement maître. `startsAt`/`endsAt` sont formatés en ISO 8601 avec offset du.
/// fuseau famille. Le paramètre `overlay=` est **réservé** (Lot 5, ignoré ici).
@JsonSerializable()
class EventUpdateEventRequestJsonMergePatch {
  const EventUpdateEventRequestJsonMergePatch({
    this.title,
    this.category,
    this.responsibleMembershipId,
    this.childIds,
    this.wholeFamily,
    this.location,
    this.description,
    this.startsAt,
    this.endsAt,
    this.allDay,
    this.recurrence,
    this.recurrenceDate,
    this.expectedVersion,
  });
  
  factory EventUpdateEventRequestJsonMergePatch.fromJson(Map<String, Object?> json) => _$EventUpdateEventRequestJsonMergePatchFromJson(json);
  
  final String? title;
  final String? category;
  final String? responsibleMembershipId;
  final List<String>? childIds;
  final bool? wholeFamily;
  final String? location;
  final String? description;
  final String? startsAt;
  final String? endsAt;
  final bool? allDay;

  /// {freq, until?, count?, excludeHolidays?} — pour scope=series.
  final Map<String, String?>? recurrence;

  /// Date d'origine de l'occurrence ciblée (scope=occurrence) ou pivot (scope=series).
  final String? recurrenceDate;
  final int? expectedVersion;

  Map<String, Object?> toJson() => _$EventUpdateEventRequestJsonMergePatchToJson(this);
}
