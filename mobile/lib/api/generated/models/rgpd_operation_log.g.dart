// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rgpd_operation_log.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RgpdOperationLog _$RgpdOperationLogFromJson(Map<String, dynamic> json) =>
    RgpdOperationLog(
      subjectUserId: json['subjectUserId'] as String?,
      requestedById: json['requestedById'] as String?,
      resultRef: json['resultRef'] as String?,
      id: json['id'] as String? ?? '',
      type: json['type'] as String? ?? '',
      status: json['status'] as String? ?? '',
      mode: json['mode'] as String? ?? '',
      occurredAt: json['occurredAt'] as String? ?? '',
    );

Map<String, dynamic> _$RgpdOperationLogToJson(RgpdOperationLog instance) =>
    <String, dynamic>{
      'id': instance.id,
      'type': instance.type,
      'status': instance.status,
      'mode': instance.mode,
      'subjectUserId': instance.subjectUserId,
      'requestedById': instance.requestedById,
      'resultRef': instance.resultRef,
      'occurredAt': instance.occurredAt,
    };
