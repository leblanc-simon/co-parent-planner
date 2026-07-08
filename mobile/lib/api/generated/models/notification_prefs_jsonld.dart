// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'notification_prefs_jsonld.g.dart';

/// US-SET-1 (Lot 7) — préférences de notification du compte courant. `/api/me/notification-prefs`.
/// est implicitement scopé à l'utilisateur du token (jamais un autre compte). Canal `email` seul en.
/// v1 ; catégorie `expenses` et canal `push` masqués (réservés v2). Quiet-hours `HH:MM` interprétées.
/// dans le fuseau de la famille active.
@JsonSerializable()
class NotificationPrefsJsonld {
  const NotificationPrefsJsonld({
    required this.object6,
    required this.object7,
    this.locale = 'fr-FR',
    this.object5,
    this.email,
    this.quietHoursStart,
    this.quietHoursEnd,
  });
  
  factory NotificationPrefsJsonld.fromJson(Map<String, Object?> json) => _$NotificationPrefsJsonldFromJson(json);
  
  /// Incorrect name has been replaced. Original name: `@context`.
  /// Incorrect name has been replaced. Original name: `@context`.
  @JsonKey(name: '@context')
  final dynamic object5;

  /// Incorrect name has been replaced. Original name: `@id`.
  @JsonKey(name: '@id')
  final String object6;

  /// Incorrect name has been replaced. Original name: `@type`.
  @JsonKey(name: '@type')
  final String object7;

  /// Activation e-mail par catégorie v1 (`custody`, `agenda`, `exchanges`, `invitation`).
  final Map<String, bool>? email;
  final String? quietHoursStart;
  final String? quietHoursEnd;
  final String locale;

  Map<String, Object?> toJson() => _$NotificationPrefsJsonldToJson(this);
}
