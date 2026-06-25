// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'logout_request_message_response.g.dart';

/// US-AUTH-2 sc.7 — déconnexion : révoque le refresh courant (corps/cookie) et efface les cookies.
@JsonSerializable()
class LogoutRequestMessageResponse {
  const LogoutRequestMessageResponse({
    this.message,
  });
  
  factory LogoutRequestMessageResponse.fromJson(Map<String, Object?> json) => _$LogoutRequestMessageResponseFromJson(json);
  
  final String? message;

  Map<String, Object?> toJson() => _$LogoutRequestMessageResponseToJson(this);
}
