// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'register_and_accept_invitation_request.g.dart';

/// Flux d'invitation simplifié (§3.3) : création de compte **et** acceptation de l'invitation en un.
/// seul appel. Endpoint **public** (préfixe `/api/auth/*`), gated par un token d'invitation valide.
///
/// L'e-mail n'est PAS fourni par le client : il est dérivé côté serveur de l'invitation (le token.
/// prouve la possession de la boîte → compte créé directement `active`, e-mail vérifié). Une session.
/// est ouverte immédiatement (cookies HttpOnly + `TokenResponse` pour le mobile) : aucun e-mail de.
/// vérification, aucune reconnexion.
@JsonSerializable()
class RegisterAndAcceptInvitationRequest {
  const RegisterAndAcceptInvitationRequest({
    this.fullName,
    this.phone,
    this.locale,
    this.token = '',
    this.password = '',
  });
  
  factory RegisterAndAcceptInvitationRequest.fromJson(Map<String, Object?> json) => _$RegisterAndAcceptInvitationRequestFromJson(json);
  
  final String token;
  final String password;
  final String? fullName;
  final String? phone;
  final String? locale;

  Map<String, Object?> toJson() => _$RegisterAndAcceptInvitationRequestToJson(this);
}
