// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'verify_email_request_message_response_jsonld.g.dart';

/// US-AUTH-1 sc.5 — vérification d'e-mail. Endpoint API unique (décision D5) appelé par la page.
/// web GET /verify-email et par le deep link mobile.
@JsonSerializable()
class VerifyEmailRequestMessageResponseJsonld {
  const VerifyEmailRequestMessageResponseJsonld({
    required this.object6,
    required this.object7,
    this.object5,
    this.message,
  });
  
  factory VerifyEmailRequestMessageResponseJsonld.fromJson(Map<String, Object?> json) => _$VerifyEmailRequestMessageResponseJsonldFromJson(json);
  
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
  final String? message;

  Map<String, Object?> toJson() => _$VerifyEmailRequestMessageResponseJsonldToJson(this);
}
