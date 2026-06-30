// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'invitation_jsonld.g.dart';

/// Vue d'une invitation pour l'admin (US-INV-1/2) : jamais le token en clair. L'e-mail est.
/// déchiffré à la volée pour l'admin habilité.
@JsonSerializable()
class InvitationJsonld {
  const InvitationJsonld({
    required this.object6,
    required this.object7,
    this.id = '',
    this.email = '',
    this.role = '',
    this.proposedAdmin = false,
    this.status = '',
    this.object5,
    this.proposedRights,
    this.expiresAt,
    this.createdAt,
  });
  
  factory InvitationJsonld.fromJson(Map<String, Object?> json) => _$InvitationJsonldFromJson(json);
  
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
  final String email;
  final String role;
  final Map<String, String>? proposedRights;
  final bool proposedAdmin;
  final String status;
  final String? expiresAt;
  final String? createdAt;

  Map<String, Object?> toJson() => _$InvitationJsonldToJson(this);
}
