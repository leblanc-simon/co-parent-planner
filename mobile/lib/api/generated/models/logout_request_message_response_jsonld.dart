// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'logout_request_message_response_jsonld.g.dart';

/// US-AUTH-2 sc.7 — déconnexion : révoque le refresh courant (corps/cookie) et efface les cookies.
@JsonSerializable()
class LogoutRequestMessageResponseJsonld {
  const LogoutRequestMessageResponseJsonld({
    required this.object6,
    required this.object7,
    this.object5,
    this.message,
  });
  
  factory LogoutRequestMessageResponseJsonld.fromJson(Map<String, Object?> json) => _$LogoutRequestMessageResponseJsonldFromJson(json);
  
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

  Map<String, Object?> toJson() => _$LogoutRequestMessageResponseJsonldToJson(this);
}
