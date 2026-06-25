// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'reset_password_request.g.dart';

/// US-AUTH-2 sc.9 — réinitialisation effective (token usage unique ; révoque tous les refresh).
@JsonSerializable()
class ResetPasswordRequest {
  const ResetPasswordRequest({
    this.token = '',
    this.password = '',
  });
  
  factory ResetPasswordRequest.fromJson(Map<String, Object?> json) => _$ResetPasswordRequestFromJson(json);
  
  final String token;
  final String password;

  Map<String, Object?> toJson() => _$ResetPasswordRequestToJson(this);
}
