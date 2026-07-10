// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'admin_step_up_step_up_request.g.dart';

/// Auth renforcée admin technique (cdc §6.2). `POST /api/admin/step-up` (ROLE_ADMIN requis) : re-saisie.
/// du mot de passe → grant step-up court à présenter dans l'en-tête `X-Admin-Step-Up` sur `/api/admin/rgpd/*`.
@JsonSerializable()
class AdminStepUpStepUpRequest {
  const AdminStepUpStepUpRequest({
    this.password = '',
  });
  
  factory AdminStepUpStepUpRequest.fromJson(Map<String, Object?> json) => _$AdminStepUpStepUpRequestFromJson(json);
  
  final String password;

  Map<String, Object?> toJson() => _$AdminStepUpStepUpRequestToJson(this);
}
