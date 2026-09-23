// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'device.g.dart';

/// Appareils du compte courant enregistrés pour le push (Lot 11 §6.2). Ressource **utilisateur** :.
/// aucun `X-Family-Id` requis, et jamais l'appareil d'un autre compte (404, sans révéler qu'il existe).
///
/// Le jeton n'est **jamais** renvoyé : il suffit à pousser du contenu vers l'appareil. La liste ne.
/// montre que ce qui permet de reconnaître un appareil avant de le révoquer.
@JsonSerializable()
class Device {
  const Device({
    this.deviceModel,
    this.appVersion,
    this.lastSeenAt,
    this.id = '',
    this.platform = '',
    this.status = '',
    this.createdAt = '',
  });
  
  factory Device.fromJson(Map<String, Object?> json) => _$DeviceFromJson(json);
  
  final String id;
  final String platform;
  final String status;
  final String? deviceModel;
  final String? appVersion;
  final String? lastSeenAt;
  final String createdAt;

  Map<String, Object?> toJson() => _$DeviceToJson(this);
}
