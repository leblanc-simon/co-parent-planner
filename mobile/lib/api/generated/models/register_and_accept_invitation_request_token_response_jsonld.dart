// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'register_and_accept_invitation_request_token_response_jsonld.g.dart';

/// Flux d'invitation simplifié (§3.3) : création de compte **et** acceptation de l'invitation en un.
/// seul appel. Endpoint **public** (préfixe `/api/auth/*`), gated par un token d'invitation valide.
///
/// L'e-mail n'est PAS fourni par le client : il est dérivé côté serveur de l'invitation (le token.
/// prouve la possession de la boîte → compte créé directement `active`, e-mail vérifié). Une session.
/// est ouverte immédiatement (cookies HttpOnly + `TokenResponse` pour le mobile) : aucun e-mail de.
/// vérification, aucune reconnexion.
@JsonSerializable()
class RegisterAndAcceptInvitationRequestTokenResponseJsonld {
  const RegisterAndAcceptInvitationRequestTokenResponseJsonld({
    required this.object6,
    required this.object7,
    this.object5,
    this.accessToken,
    this.refreshToken,
    this.expiresIn,
    this.tokenType,
  });
  
  factory RegisterAndAcceptInvitationRequestTokenResponseJsonld.fromJson(Map<String, Object?> json) => _$RegisterAndAcceptInvitationRequestTokenResponseJsonldFromJson(json);
  
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
  final String? accessToken;
  final String? refreshToken;
  final int? expiresIn;
  final String? tokenType;

  Map<String, Object?> toJson() => _$RegisterAndAcceptInvitationRequestTokenResponseJsonldToJson(this);
}
