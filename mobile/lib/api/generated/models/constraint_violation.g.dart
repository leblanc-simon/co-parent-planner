// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'constraint_violation.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ConstraintViolation _$ConstraintViolationFromJson(Map<String, dynamic> json) =>
    ConstraintViolation(
      violations: (json['violations'] as List<dynamic>?)
          ?.map((e) => Violations.fromJson(e as Map<String, dynamic>))
          .toList(),
      detail: json['detail'] as String?,
      type: json['type'] as String?,
      title: json['title'] as String?,
      instance: json['instance'] as String?,
      status: (json['status'] as num?)?.toInt() ?? 422,
    );

Map<String, dynamic> _$ConstraintViolationToJson(
  ConstraintViolation instance,
) => <String, dynamic>{
  'status': instance.status,
  'violations': instance.violations,
  'detail': instance.detail,
  'type': instance.type,
  'title': instance.title,
  'instance': instance.instance,
};
