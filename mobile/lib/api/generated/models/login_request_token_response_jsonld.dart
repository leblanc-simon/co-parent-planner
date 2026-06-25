// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'login_request_token_response_jsonld.g.dart';

/// US-AUTH-2 sc.1 — connexion : JWT + refresh rotatif (rate-limit + lockout, anti-énumération).
@JsonSerializable()
class LoginRequestTokenResponseJsonld {
  const LoginRequestTokenResponseJsonld({
    required this.object6,
    required this.object7,
    this.object5,
    this.accessToken,
    this.refreshToken,
    this.expiresIn,
    this.tokenType,
  });
  
  factory LoginRequestTokenResponseJsonld.fromJson(Map<String, Object?> json) => _$LoginRequestTokenResponseJsonldFromJson(json);
  
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

  Map<String, Object?> toJson() => _$LoginRequestTokenResponseJsonldToJson(this);
}
