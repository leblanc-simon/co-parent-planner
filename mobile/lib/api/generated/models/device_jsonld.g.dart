// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'device_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeviceJsonld _$DeviceJsonldFromJson(Map<String, dynamic> json) => DeviceJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  id: json['id'] as String? ?? '',
  platform: json['platform'] as String? ?? '',
  status: json['status'] as String? ?? '',
  createdAt: json['createdAt'] as String? ?? '',
  object5: json['@context'],
  deviceModel: json['deviceModel'] as String?,
  appVersion: json['appVersion'] as String?,
  lastSeenAt: json['lastSeenAt'] as String?,
);

Map<String, dynamic> _$DeviceJsonldToJson(DeviceJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'id': instance.id,
      'platform': instance.platform,
      'status': instance.status,
      'deviceModel': instance.deviceModel,
      'appVersion': instance.appVersion,
      'lastSeenAt': instance.lastSeenAt,
      'createdAt': instance.createdAt,
    };
