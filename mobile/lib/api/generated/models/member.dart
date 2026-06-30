// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'member.g.dart';

/// Vue d'un membre d'une famille (US-MBR-1) : rôle, admin, couleur, matrice de droits **effectifs**,.
/// et identité 🔒 déchiffrée à la volée pour les membres habilités (jamais en clair dans un dump).
@JsonSerializable()
class Member {
  const Member({
    this.displayColor,
    this.fullName,
    this.email,
    this.phone,
    this.childId,
    this.rights,
    this.id = '',
    this.userId = '',
    this.role = '',
    this.isAdmin = false,
    this.self = false,
  });
  
  factory Member.fromJson(Map<String, Object?> json) => _$MemberFromJson(json);
  
  final String id;
  final String userId;
  final String role;
  final bool isAdmin;
  final String? displayColor;
  final String? fullName;
  final String? email;
  final String? phone;
  final String? childId;

  /// domaine → niveau effectif
  final Map<String, String>? rights;

  /// Vrai s'il s'agit du membre correspondant à l'utilisateur courant.
  final bool self;

  Map<String, Object?> toJson() => _$MemberToJson(this);
}
