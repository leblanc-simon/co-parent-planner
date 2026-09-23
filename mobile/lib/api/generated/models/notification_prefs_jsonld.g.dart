// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'notification_prefs_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

NotificationPrefsJsonld _$NotificationPrefsJsonldFromJson(
  Map<String, dynamic> json,
) => NotificationPrefsJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  locale: json['locale'] as String? ?? 'fr-FR',
  object5: json['@context'],
  email: (json['email'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as bool),
  ),
  push: (json['push'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as bool),
  ),
  quietHoursStart: json['quietHoursStart'] as String?,
  quietHoursEnd: json['quietHoursEnd'] as String?,
);

Map<String, dynamic> _$NotificationPrefsJsonldToJson(
  NotificationPrefsJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'email': instance.email,
  'push': instance.push,
  'quietHoursStart': instance.quietHoursStart,
  'quietHoursEnd': instance.quietHoursEnd,
  'locale': instance.locale,
};
