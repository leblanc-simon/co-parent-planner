// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'admin_step_up_jsonld.g.dart';

/// Auth renforcée admin technique (cdc §6.2). `POST /api/admin/step-up` (ROLE_ADMIN requis) : re-saisie.
/// du mot de passe → grant step-up court à présenter dans l'en-tête `X-Admin-Step-Up` sur `/api/admin/rgpd/*`.
@JsonSerializable()
class AdminStepUpJsonld {
  const AdminStepUpJsonld({
    required this.object6,
    required this.object7,
    this.token = '',
    this.expiresIn = 0,
    this.object5,
  });
  
  factory AdminStepUpJsonld.fromJson(Map<String, Object?> json) => _$AdminStepUpJsonldFromJson(json);
  
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
  final String token;
  final int expiresIn;

  Map<String, Object?> toJson() => _$AdminStepUpJsonldToJson(this);
}
