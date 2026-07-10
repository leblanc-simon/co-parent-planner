// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rgpd_operation_log_jsonld.g.dart';

/// Une opération du journal RGPD (US-RGPD-2), lecture seule. `GET /admin/rgpd/operations`.
/// (admin technique + step-up), filtrable par `type`/`status`, paginé (`page`, `itemsPerPage`).
@JsonSerializable()
class RgpdOperationLogJsonld {
  const RgpdOperationLogJsonld({
    required this.object6,
    required this.object7,
    this.id = '',
    this.type = '',
    this.status = '',
    this.mode = '',
    this.occurredAt = '',
    this.object5,
    this.subjectUserId,
    this.requestedById,
    this.resultRef,
  });
  
  factory RgpdOperationLogJsonld.fromJson(Map<String, Object?> json) => _$RgpdOperationLogJsonldFromJson(json);
  
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
  final String id;
  final String type;
  final String status;
  final String mode;
  final String? subjectUserId;
  final String? requestedById;
  final String? resultRef;
  final String occurredAt;

  Map<String, Object?> toJson() => _$RgpdOperationLogJsonldToJson(this);
}
