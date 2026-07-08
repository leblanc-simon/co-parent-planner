// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'notification_prefs.g.dart';

/// US-SET-1 (Lot 7) — préférences de notification du compte courant. `/api/me/notification-prefs`.
/// est implicitement scopé à l'utilisateur du token (jamais un autre compte). Canal `email` seul en.
/// v1 ; catégorie `expenses` et canal `push` masqués (réservés v2). Quiet-hours `HH:MM` interprétées.
/// dans le fuseau de la famille active.
@JsonSerializable()
class NotificationPrefs {
  const NotificationPrefs({
    this.locale = 'fr-FR',
    this.email,
    this.quietHoursStart,
    this.quietHoursEnd,
  });
  
  factory NotificationPrefs.fromJson(Map<String, Object?> json) => _$NotificationPrefsFromJson(json);
  
  /// Activation e-mail par catégorie v1 (`custody`, `agenda`, `exchanges`, `invitation`).
  final Map<String, bool>? email;
  final String? quietHoursStart;
  final String? quietHoursEnd;
  final String locale;

  Map<String, Object?> toJson() => _$NotificationPrefsToJson(this);
}
