// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'verify_email_request_message_response.g.dart';

/// US-AUTH-1 sc.5 — vérification d'e-mail. Endpoint API unique (décision D5) appelé par la page.
/// web GET /verify-email et par le deep link mobile.
@JsonSerializable()
class VerifyEmailRequestMessageResponse {
  const VerifyEmailRequestMessageResponse({
    this.message,
  });
  
  factory VerifyEmailRequestMessageResponse.fromJson(Map<String, Object?> json) => _$VerifyEmailRequestMessageResponseFromJson(json);
  
  final String? message;

  Map<String, Object?> toJson() => _$VerifyEmailRequestMessageResponseToJson(this);
}
