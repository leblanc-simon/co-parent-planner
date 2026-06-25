// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'violations.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Violations _$ViolationsFromJson(Map<String, dynamic> json) => Violations(
  propertyPath: json['propertyPath'] as String,
  message: json['message'] as String,
  code: json['code'] as String?,
  hint: json['hint'] as String?,
  payload: json['payload'],
);

Map<String, dynamic> _$ViolationsToJson(Violations instance) =>
    <String, dynamic>{
      'propertyPath': instance.propertyPath,
      'message': instance.message,
      'code': instance.code,
      'hint': instance.hint,
      'payload': instance.payload,
    };
