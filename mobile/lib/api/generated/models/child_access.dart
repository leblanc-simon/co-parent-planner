// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'child_access.g.dart';

/// Une entrée du bloc « Accès & droits » d'un enfant (US-CH-2) — sous-ressource dédiée (§5.4 #5).
@JsonSerializable()
class ChildAccess {
  const ChildAccess({
    this.displayColor,
    this.membershipId = '',
    this.name = '',
    this.role = '',
    this.level = '',
    this.isOwnProfile = false,
  });
  
  factory ChildAccess.fromJson(Map<String, Object?> json) => _$ChildAccessFromJson(json);
  
  final String membershipId;
  final String name;
  final String role;
  final String level;
  final bool isOwnProfile;
  final String? displayColor;

  Map<String, Object?> toJson() => _$ChildAccessToJson(this);
}
