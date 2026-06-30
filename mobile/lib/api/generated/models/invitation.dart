// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'invitation.g.dart';

/// Vue d'une invitation pour l'admin (US-INV-1/2) : jamais le token en clair. L'e-mail est.
/// déchiffré à la volée pour l'admin habilité.
@JsonSerializable()
class Invitation {
  const Invitation({
    this.proposedRights,
    this.expiresAt,
    this.createdAt,
    this.id = '',
    this.email = '',
    this.role = '',
    this.proposedAdmin = false,
    this.status = '',
  });
  
  factory Invitation.fromJson(Map<String, Object?> json) => _$InvitationFromJson(json);
  
  final String id;
  final String email;
  final String role;
  final Map<String, String>? proposedRights;
  final bool proposedAdmin;
  final String status;
  final String? expiresAt;
  final String? createdAt;

  Map<String, Object?> toJson() => _$InvitationToJson(this);
}
