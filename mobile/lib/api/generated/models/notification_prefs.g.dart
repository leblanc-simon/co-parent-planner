// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'notification_prefs.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

NotificationPrefs _$NotificationPrefsFromJson(Map<String, dynamic> json) =>
    NotificationPrefs(
      locale: json['locale'] as String? ?? 'fr-FR',
      email: (json['email'] as Map<String, dynamic>?)?.map(
        (k, e) => MapEntry(k, e as bool),
      ),
      push: (json['push'] as Map<String, dynamic>?)?.map(
        (k, e) => MapEntry(k, e as bool),
      ),
      quietHoursStart: json['quietHoursStart'] as String?,
      quietHoursEnd: json['quietHoursEnd'] as String?,
    );

Map<String, dynamic> _$NotificationPrefsToJson(NotificationPrefs instance) =>
    <String, dynamic>{
      'email': instance.email,
      'push': instance.push,
      'quietHoursStart': instance.quietHoursStart,
      'quietHoursEnd': instance.quietHoursEnd,
      'locale': instance.locale,
    };
