// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'member_update_membership_request_json_merge_patch_role.dart';

part 'member_update_membership_request_json_merge_patch.g.dart';

/// Vue d'un membre d'une famille (US-MBR-1) : rôle, admin, couleur, matrice de droits **effectifs**,.
/// et identité 🔒 déchiffrée à la volée pour les membres habilités (jamais en clair dans un dump).
@JsonSerializable()
class MemberUpdateMembershipRequestJsonMergePatch {
  const MemberUpdateMembershipRequestJsonMergePatch({
    this.role,
    this.isAdmin,
    this.rights,
    this.displayColor,
  });
  
  factory MemberUpdateMembershipRequestJsonMergePatch.fromJson(Map<String, Object?> json) => _$MemberUpdateMembershipRequestJsonMergePatchFromJson(json);
  
  final MemberUpdateMembershipRequestJsonMergePatchRole? role;
  final bool? isAdmin;

  /// domaine → niveau proposé
  final Map<String, String>? rights;
  final String? displayColor;

  Map<String, Object?> toJson() => _$MemberUpdateMembershipRequestJsonMergePatchToJson(this);
}
