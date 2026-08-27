// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'event.g.dart';

/// Événement / occurrence d'agenda exposé (Lot 4 §6.1). `GET /families/{id}/events` renvoie les.
/// **occurrences** sur [from, to] (une par occurrence de série) ; `POST` crée un événement ou une.
/// série et renvoie l'événement maître. `startsAt`/`endsAt` sont formatés en ISO 8601 avec offset du.
/// fuseau famille. Le paramètre `overlay=` fusionne en lecture seule les événements des.
/// familles liées activées (Lot 5).
@JsonSerializable()
class Event {
  const Event({
    this.seriesId,
    this.responsibleMembershipId,
    this.childIds,
    this.location,
    this.description,
    this.endsAt,
    this.recurrenceDate,
    this.recurrence,
    this.id = '',
    this.title = '',
    this.category = '',
    this.wholeFamily = false,
    this.startsAt = '',
    this.allDay = false,
    this.isRecurring = false,
    this.isOverride = false,
    this.version = 1,
  });
  
  factory Event.fromJson(Map<String, Object?> json) => _$EventFromJson(json);
  
  final String id;
  final String? seriesId;
  final String title;
  final String category;
  final String? responsibleMembershipId;
  final bool wholeFamily;
  final List<String>? childIds;
  final String? location;
  final String? description;
  final String startsAt;
  final String? endsAt;
  final bool allDay;
  final String? recurrenceDate;
  final bool isRecurring;
  final bool isOverride;

  /// Version de la ligne à renvoyer dans `expectedVersion` d'un PATCH (verrou optimiste, §4.3).
  final int version;
  final Map<String, dynamic>? recurrence;

  Map<String, Object?> toJson() => _$EventToJson(this);
}
