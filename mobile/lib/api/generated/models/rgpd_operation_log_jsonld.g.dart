// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rgpd_operation_log_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RgpdOperationLogJsonld _$RgpdOperationLogJsonldFromJson(
  Map<String, dynamic> json,
) => RgpdOperationLogJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  id: json['id'] as String? ?? '',
  type: json['type'] as String? ?? '',
  status: json['status'] as String? ?? '',
  mode: json['mode'] as String? ?? '',
  occurredAt: json['occurredAt'] as String? ?? '',
  object5: json['@context'],
  subjectUserId: json['subjectUserId'] as String?,
  requestedById: json['requestedById'] as String?,
  resultRef: json['resultRef'] as String?,
);

Map<String, dynamic> _$RgpdOperationLogJsonldToJson(
  RgpdOperationLogJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'id': instance.id,
  'type': instance.type,
  'status': instance.status,
  'mode': instance.mode,
  'subjectUserId': instance.subjectUserId,
  'requestedById': instance.requestedById,
  'resultRef': instance.resultRef,
  'occurredAt': instance.occurredAt,
};
