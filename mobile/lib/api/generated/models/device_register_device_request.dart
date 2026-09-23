// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device_register_device_request_platform.dart';

part 'device_register_device_request.g.dart';

/// Appareils du compte courant enregistrés pour le push (Lot 11 §6.2). Ressource **utilisateur** :.
/// aucun `X-Family-Id` requis, et jamais l'appareil d'un autre compte (404, sans révéler qu'il existe).
///
/// Le jeton n'est **jamais** renvoyé : il suffit à pousser du contenu vers l'appareil. La liste ne.
/// montre que ce qui permet de reconnaître un appareil avant de le révoquer.
@JsonSerializable()
class DeviceRegisterDeviceRequest {
  const DeviceRegisterDeviceRequest({
    required this.platform,
    required this.token,
    this.installationId,
    this.appVersion,
    this.deviceModel,
    this.locale,
  });
  
  factory DeviceRegisterDeviceRequest.fromJson(Map<String, Object?> json) => _$DeviceRegisterDeviceRequestFromJson(json);
  
  final DeviceRegisterDeviceRequestPlatform? platform;

  /// Jeton FCM (quelques centaines de caractères) ; au-delà de 4 Kio ce n'est pas un jeton.
  final String? token;

  /// Identifiant stable de l'installation de l'app : détecte la rotation du jeton (US-PUSH-2).
  final String? installationId;
  final String? appVersion;
  final String? deviceModel;
  final String? locale;

  Map<String, Object?> toJson() => _$DeviceRegisterDeviceRequestToJson(this);
}
