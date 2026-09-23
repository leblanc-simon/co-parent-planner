// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'device.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Device _$DeviceFromJson(Map<String, dynamic> json) => Device(
  deviceModel: json['deviceModel'] as String?,
  appVersion: json['appVersion'] as String?,
  lastSeenAt: json['lastSeenAt'] as String?,
  id: json['id'] as String? ?? '',
  platform: json['platform'] as String? ?? '',
  status: json['status'] as String? ?? '',
  createdAt: json['createdAt'] as String? ?? '',
);

Map<String, dynamic> _$DeviceToJson(Device instance) => <String, dynamic>{
  'id': instance.id,
  'platform': instance.platform,
  'status': instance.status,
  'deviceModel': instance.deviceModel,
  'appVersion': instance.appVersion,
  'lastSeenAt': instance.lastSeenAt,
  'createdAt': instance.createdAt,
};
