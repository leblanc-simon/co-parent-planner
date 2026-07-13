// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'invitation_onboarding_token_response_jsonld.g.dart';

/// Flux d'invitation simplifié (§3.3), variante *compte existant* : connexion **et** acceptation en.
/// un seul appel. Endpoint **public** (préfixe `/api/auth/*`), gated par un token d'invitation valide.
///
/// Frontière de sécurité : sur un compte préexistant, le **mot de passe est exigé** (2ᵉ facteur) — un.
/// lien d'invitation transféré/fuité ne peut pas détourner un compte tiers. L'e-mail est dérivé de.
/// l'invitation, jamais du client.
@JsonSerializable()
class InvitationOnboardingTokenResponseJsonld {
  const InvitationOnboardingTokenResponseJsonld({
    required this.object6,
    required this.object7,
    this.object5,
    this.accessToken,
    this.refreshToken,
    this.expiresIn,
    this.tokenType,
  });
  
  factory InvitationOnboardingTokenResponseJsonld.fromJson(Map<String, Object?> json) => _$InvitationOnboardingTokenResponseJsonldFromJson(json);
  
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

  Map<String, Object?> toJson() => _$InvitationOnboardingTokenResponseJsonldToJson(this);
}
