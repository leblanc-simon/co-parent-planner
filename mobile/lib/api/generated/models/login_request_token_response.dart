// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'login_request_token_response.g.dart';

/// US-AUTH-2 sc.1 — connexion : JWT + refresh rotatif (rate-limit + lockout, anti-énumération).
@JsonSerializable()
class LoginRequestTokenResponse {
  const LoginRequestTokenResponse({
    this.accessToken,
    this.refreshToken,
    this.expiresIn,
    this.tokenType,
  });
  
  factory LoginRequestTokenResponse.fromJson(Map<String, Object?> json) => _$LoginRequestTokenResponseFromJson(json);
  
  final String? accessToken;
  final String? refreshToken;
  final int? expiresIn;
  final String? tokenType;

  Map<String, Object?> toJson() => _$LoginRequestTokenResponseToJson(this);
}
