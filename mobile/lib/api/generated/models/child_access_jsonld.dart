// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'child_access_jsonld.g.dart';

/// Une entrée du bloc « Accès & droits » d'un enfant (US-CH-2) — sous-ressource dédiée (§5.4 #5).
@JsonSerializable()
class ChildAccessJsonld {
  const ChildAccessJsonld({
    required this.object6,
    required this.object7,
    this.membershipId = '',
    this.name = '',
    this.role = '',
    this.level = '',
    this.isOwnProfile = false,
    this.object5,
    this.displayColor,
  });
  
  factory ChildAccessJsonld.fromJson(Map<String, Object?> json) => _$ChildAccessJsonldFromJson(json);
  
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
  final String membershipId;
  final String name;
  final String role;
  final String level;
  final bool isOwnProfile;
  final String? displayColor;

  Map<String, Object?> toJson() => _$ChildAccessJsonldToJson(this);
}
