// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'device_jsonld.g.dart';

/// Appareils du compte courant enregistrés pour le push (Lot 11 §6.2). Ressource **utilisateur** :.
/// aucun `X-Family-Id` requis, et jamais l'appareil d'un autre compte (404, sans révéler qu'il existe).
///
/// Le jeton n'est **jamais** renvoyé : il suffit à pousser du contenu vers l'appareil. La liste ne.
/// montre que ce qui permet de reconnaître un appareil avant de le révoquer.
@JsonSerializable()
class DeviceJsonld {
  const DeviceJsonld({
    required this.object6,
    required this.object7,
    this.id = '',
    this.platform = '',
    this.status = '',
    this.createdAt = '',
    this.object5,
    this.deviceModel,
    this.appVersion,
    this.lastSeenAt,
  });
  
  factory DeviceJsonld.fromJson(Map<String, Object?> json) => _$DeviceJsonldFromJson(json);
  
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
  final String id;
  final String platform;
  final String status;
  final String? deviceModel;
  final String? appVersion;
  final String? lastSeenAt;
  final String createdAt;

  Map<String, Object?> toJson() => _$DeviceJsonldToJson(this);
}
