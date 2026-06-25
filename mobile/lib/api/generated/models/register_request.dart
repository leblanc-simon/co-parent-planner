// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'register_request.g.dart';

/// US-AUTH-1 — inscription (anti-énumération, e-mail de vérification async, aucun token).
@JsonSerializable()
class RegisterRequest {
  const RegisterRequest({
    this.fullName,
    this.phone,
    this.locale,
    this.email = '',
    this.password = '',
  });
  
  factory RegisterRequest.fromJson(Map<String, Object?> json) => _$RegisterRequestFromJson(json);
  
  final String email;
  final String password;
  final String? fullName;
  final String? phone;
  final String? locale;

  Map<String, Object?> toJson() => _$RegisterRequestToJson(this);
}
