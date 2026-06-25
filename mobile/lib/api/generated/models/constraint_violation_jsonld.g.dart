// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'constraint_violation_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ConstraintViolationJsonld _$ConstraintViolationJsonldFromJson(
  Map<String, dynamic> json,
) => ConstraintViolationJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  status: (json['status'] as num?)?.toInt() ?? 422,
  object5: json['@context'],
  violations: (json['violations'] as List<dynamic>?)
      ?.map((e) => Violations2.fromJson(e as Map<String, dynamic>))
      .toList(),
  detail: json['detail'] as String?,
  description: json['description'] as String?,
  type: json['type'] as String?,
  title: json['title'] as String?,
  instance: json['instance'] as String?,
);

Map<String, dynamic> _$ConstraintViolationJsonldToJson(
  ConstraintViolationJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'status': instance.status,
  'violations': instance.violations,
  'detail': instance.detail,
  'description': instance.description,
  'type': instance.type,
  'title': instance.title,
  'instance': instance.instance,
};
