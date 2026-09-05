// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'member_jsonld.g.dart';

/// Vue d'un membre d'une famille (US-MBR-1) : rôle, admin, couleur, matrice de droits **effectifs**,.
/// et identité 🔒 déchiffrée à la volée pour les membres habilités (jamais en clair dans un dump).
@JsonSerializable()
class MemberJsonld {
  const MemberJsonld({
    required this.object6,
    required this.object7,
    this.id = '',
    this.userId = '',
    this.role = '',
    this.isAdmin = false,
    this.self = false,
    this.object5,
    this.displayColor,
    this.fullName,
    this.email,
    this.phone,
    this.childId,
    this.rights,
    this.partnerMembershipId,
    this.partnerName,
  });
  
  factory MemberJsonld.fromJson(Map<String, Object?> json) => _$MemberJsonldFromJson(json);
  
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

  /// Couple parent ↔ beau-parent (E1). Le champ est rendu **des deux côtés** : le beau-parent porte.
  /// la relation, le parent la voit en miroir — sans quoi la moitié des écrans devrait faire la.
  /// jointure elle-même.
  final String? partnerMembershipId;

  /// Nom 🔒 déchiffré du conjoint, ou son e-mail à défaut ; `null` sans association.
  final String? partnerName;

  Map<String, Object?> toJson() => _$MemberJsonldToJson(this);
}
