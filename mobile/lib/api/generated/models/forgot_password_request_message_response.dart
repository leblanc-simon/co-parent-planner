// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'forgot_password_request_message_response.g.dart';

/// US-AUTH-2 sc.8 — demande de reset (réponse toujours générique, anti-énumération, rate-limité).
@JsonSerializable()
class ForgotPasswordRequestMessageResponse {
  const ForgotPasswordRequestMessageResponse({
    this.message,
  });
  
  factory ForgotPasswordRequestMessageResponse.fromJson(Map<String, Object?> json) => _$ForgotPasswordRequestMessageResponseFromJson(json);
  
  final String? message;

  Map<String, Object?> toJson() => _$ForgotPasswordRequestMessageResponseToJson(this);
}
