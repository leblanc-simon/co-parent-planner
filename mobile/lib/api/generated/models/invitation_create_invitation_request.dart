// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'invitation_create_invitation_request_role.dart';

part 'invitation_create_invitation_request.g.dart';

/// Vue d'une invitation pour l'admin (US-INV-1/2) : jamais le token en clair. L'e-mail est.
/// déchiffré à la volée pour l'admin habilité.
@JsonSerializable()
class InvitationCreateInvitationRequest {
  const InvitationCreateInvitationRequest({
    required this.email,
    required this.role,
    this.rights,
    this.isAdmin,
  });
  
  factory InvitationCreateInvitationRequest.fromJson(Map<String, Object?> json) => _$InvitationCreateInvitationRequestFromJson(json);
  
  final String? email;
  final InvitationCreateInvitationRequestRole? role;

  /// domaine → niveau proposé
  final Map<String, String>? rights;

  /// Admin proposé (honoré seulement pour role=parent).
  final bool? isAdmin;

  Map<String, Object?> toJson() => _$InvitationCreateInvitationRequestToJson(this);
}
