// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'invitation_onboarding_token_response.g.dart';

/// Flux d'invitation simplifié (§3.3), variante *compte existant* : connexion **et** acceptation en.
/// un seul appel. Endpoint **public** (préfixe `/api/auth/*`), gated par un token d'invitation valide.
///
/// Frontière de sécurité : sur un compte préexistant, le **mot de passe est exigé** (2ᵉ facteur) — un.
/// lien d'invitation transféré/fuité ne peut pas détourner un compte tiers. L'e-mail est dérivé de.
/// l'invitation, jamais du client.
@JsonSerializable()
class InvitationOnboardingTokenResponse {
  const InvitationOnboardingTokenResponse({
    this.accessToken,
    this.refreshToken,
    this.expiresIn,
    this.tokenType,
  });
  
  factory InvitationOnboardingTokenResponse.fromJson(Map<String, Object?> json) => _$InvitationOnboardingTokenResponseFromJson(json);
  
  final String? accessToken;
  final String? refreshToken;
  final int? expiresIn;
  final String? tokenType;

  Map<String, Object?> toJson() => _$InvitationOnboardingTokenResponseToJson(this);
}
