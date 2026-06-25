// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'reset_password_request_message_response.g.dart';

/// US-AUTH-2 sc.9 — réinitialisation effective (token usage unique ; révoque tous les refresh).
@JsonSerializable()
class ResetPasswordRequestMessageResponse {
  const ResetPasswordRequestMessageResponse({
    this.message,
  });
  
  factory ResetPasswordRequestMessageResponse.fromJson(Map<String, Object?> json) => _$ResetPasswordRequestMessageResponseFromJson(json);
  
  final String? message;

  Map<String, Object?> toJson() => _$ResetPasswordRequestMessageResponseToJson(this);
}
