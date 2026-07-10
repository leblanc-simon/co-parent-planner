// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'admin_step_up.g.dart';

/// Auth renforcée admin technique (cdc §6.2). `POST /api/admin/step-up` (ROLE_ADMIN requis) : re-saisie.
/// du mot de passe → grant step-up court à présenter dans l'en-tête `X-Admin-Step-Up` sur `/api/admin/rgpd/*`.
@JsonSerializable()
class AdminStepUp {
  const AdminStepUp({
    this.token = '',
    this.expiresIn = 0,
  });
  
  factory AdminStepUp.fromJson(Map<String, Object?> json) => _$AdminStepUpFromJson(json);
  
  final String token;
  final int expiresIn;

  Map<String, Object?> toJson() => _$AdminStepUpToJson(this);
}
