// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'login_request.g.dart';

/// US-AUTH-2 sc.1 — connexion : JWT + refresh rotatif (rate-limit + lockout, anti-énumération).
@JsonSerializable()
class LoginRequest {
  const LoginRequest({
    this.email = '',
    this.password = '',
  });
  
  factory LoginRequest.fromJson(Map<String, Object?> json) => _$LoginRequestFromJson(json);
  
  final String email;
  final String password;

  Map<String, Object?> toJson() => _$LoginRequestToJson(this);
}
