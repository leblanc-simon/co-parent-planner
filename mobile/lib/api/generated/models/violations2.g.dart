// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'violations2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Violations2 _$Violations2FromJson(Map<String, dynamic> json) => Violations2(
  propertyPath: json['propertyPath'] as String,
  message: json['message'] as String,
  code: json['code'] as String?,
  hint: json['hint'] as String?,
  payload: json['payload'],
);

Map<String, dynamic> _$Violations2ToJson(Violations2 instance) =>
    <String, dynamic>{
      'propertyPath': instance.propertyPath,
      'message': instance.message,
      'code': instance.code,
      'hint': instance.hint,
      'payload': instance.payload,
    };
