// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'device_register_device_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeviceRegisterDeviceRequest _$DeviceRegisterDeviceRequestFromJson(
  Map<String, dynamic> json,
) => DeviceRegisterDeviceRequest(
  platform: json['platform'] == null
      ? null
      : DeviceRegisterDeviceRequestPlatform.fromJson(
          json['platform'] as String?,
        ),
  token: json['token'] as String?,
  installationId: json['installationId'] as String?,
  appVersion: json['appVersion'] as String?,
  deviceModel: json['deviceModel'] as String?,
  locale: json['locale'] as String?,
);

Map<String, dynamic> _$DeviceRegisterDeviceRequestToJson(
  DeviceRegisterDeviceRequest instance,
) => <String, dynamic>{
  'platform': instance.platform,
  'token': instance.token,
  'installationId': instance.installationId,
  'appVersion': instance.appVersion,
  'deviceModel': instance.deviceModel,
  'locale': instance.locale,
};
