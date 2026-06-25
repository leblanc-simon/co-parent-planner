// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'refresh_request_token_response_jsonld.g.dart';

/// US-AUTH-2 sc.5/6 — rafraîchissement rotatif (token depuis le corps OU le cookie HttpOnly).
@JsonSerializable()
class RefreshRequestTokenResponseJsonld {
  const RefreshRequestTokenResponseJsonld({
    required this.object6,
    required this.object7,
    this.object5,
    this.accessToken,
    this.refreshToken,
    this.expiresIn,
    this.tokenType,
  });
  
  factory RefreshRequestTokenResponseJsonld.fromJson(Map<String, Object?> json) => _$RefreshRequestTokenResponseJsonldFromJson(json);
  
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

  Map<String, Object?> toJson() => _$RefreshRequestTokenResponseJsonldToJson(this);
}
