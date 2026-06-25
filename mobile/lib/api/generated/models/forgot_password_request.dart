// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'forgot_password_request.g.dart';

/// US-AUTH-2 sc.8 — demande de reset (réponse toujours générique, anti-énumération, rate-limité).
@JsonSerializable()
class ForgotPasswordRequest {
  const ForgotPasswordRequest({
    this.email = '',
  });
  
  factory ForgotPasswordRequest.fromJson(Map<String, Object?> json) => _$ForgotPasswordRequestFromJson(json);
  
  final String email;

  Map<String, Object?> toJson() => _$ForgotPasswordRequestToJson(this);
}
