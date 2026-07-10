// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rgpd_operation_log.g.dart';

/// Une opération du journal RGPD (US-RGPD-2), lecture seule. `GET /admin/rgpd/operations`.
/// (admin technique + step-up), filtrable par `type`/`status`, paginé (`page`, `itemsPerPage`).
@JsonSerializable()
class RgpdOperationLog {
  const RgpdOperationLog({
    this.subjectUserId,
    this.requestedById,
    this.resultRef,
    this.id = '',
    this.type = '',
    this.status = '',
    this.mode = '',
    this.occurredAt = '',
  });
  
  factory RgpdOperationLog.fromJson(Map<String, Object?> json) => _$RgpdOperationLogFromJson(json);
  
  final String id;
  final String type;
  final String status;
  final String mode;
  final String? subjectUserId;
  final String? requestedById;
  final String? resultRef;
  final String occurredAt;

  Map<String, Object?> toJson() => _$RgpdOperationLogToJson(this);
}
