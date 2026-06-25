// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'register_request_message_response.g.dart';

/// US-AUTH-1 — inscription (anti-énumération, e-mail de vérification async, aucun token).
@JsonSerializable()
class RegisterRequestMessageResponse {
  const RegisterRequestMessageResponse({
    this.message,
  });
  
  factory RegisterRequestMessageResponse.fromJson(Map<String, Object?> json) => _$RegisterRequestMessageResponseFromJson(json);
  
  final String? message;

  Map<String, Object?> toJson() => _$RegisterRequestMessageResponseToJson(this);
}
