// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'register_and_accept_invitation_request_token_response.g.dart';

/// Flux d'invitation simplifié (§3.3) : création de compte **et** acceptation de l'invitation en un.
/// seul appel. Endpoint **public** (préfixe `/api/auth/*`), gated par un token d'invitation valide.
///
/// L'e-mail n'est PAS fourni par le client : il est dérivé côté serveur de l'invitation (le token.
/// prouve la possession de la boîte → compte créé directement `active`, e-mail vérifié). Une session.
/// est ouverte immédiatement (cookies HttpOnly + `TokenResponse` pour le mobile) : aucun e-mail de.
/// vérification, aucune reconnexion.
@JsonSerializable()
class RegisterAndAcceptInvitationRequestTokenResponse {
  const RegisterAndAcceptInvitationRequestTokenResponse({
    this.accessToken,
    this.refreshToken,
    this.expiresIn,
    this.tokenType,
  });
  
  factory RegisterAndAcceptInvitationRequestTokenResponse.fromJson(Map<String, Object?> json) => _$RegisterAndAcceptInvitationRequestTokenResponseFromJson(json);
  
  final String? accessToken;
  final String? refreshToken;
  final int? expiresIn;
  final String? tokenType;

  Map<String, Object?> toJson() => _$RegisterAndAcceptInvitationRequestTokenResponseToJson(this);
}
