// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'verify_email_request.g.dart';

/// US-AUTH-1 sc.5 — vérification d'e-mail. Endpoint API unique (décision D5) appelé par la page.
/// web GET /verify-email et par le deep link mobile.
@JsonSerializable()
class VerifyEmailRequest {
  const VerifyEmailRequest({
    this.token = '',
  });
  
  factory VerifyEmailRequest.fromJson(Map<String, Object?> json) => _$VerifyEmailRequestFromJson(json);
  
  final String token;

  Map<String, Object?> toJson() => _$VerifyEmailRequestToJson(this);
}
