// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'refresh_request_token_response.g.dart';

/// US-AUTH-2 sc.5/6 — rafraîchissement rotatif (token depuis le corps OU le cookie HttpOnly).
@JsonSerializable()
class RefreshRequestTokenResponse {
  const RefreshRequestTokenResponse({
    this.accessToken,
    this.refreshToken,
    this.expiresIn,
    this.tokenType,
  });
  
  factory RefreshRequestTokenResponse.fromJson(Map<String, Object?> json) => _$RefreshRequestTokenResponseFromJson(json);
  
  final String? accessToken;
  final String? refreshToken;
  final int? expiresIn;
  final String? tokenType;

  Map<String, Object?> toJson() => _$RefreshRequestTokenResponseToJson(this);
}
