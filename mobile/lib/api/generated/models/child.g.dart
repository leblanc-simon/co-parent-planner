// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'child.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Child _$ChildFromJson(Map<String, dynamic> json) => Child(
  birthDate: json['birthDate'] as String?,
  age: (json['age'] as num?)?.toInt(),
  schoolLabel: json['schoolLabel'] as String?,
  info: (json['info'] as List<dynamic>?)
      ?.map((e) => Map<String, String>.from(e as Map))
      .toList(),
  id: json['id'] as String? ?? '',
  firstName: json['firstName'] as String? ?? '',
  color: json['color'] as String? ?? '',
  initial: json['initial'] as String? ?? '',
  version: (json['version'] as num?)?.toInt() ?? 1,
);

Map<String, dynamic> _$ChildToJson(Child instance) => <String, dynamic>{
  'id': instance.id,
  'firstName': instance.firstName,
  'color': instance.color,
  'initial': instance.initial,
  'birthDate': instance.birthDate,
  'age': instance.age,
  'schoolLabel': instance.schoolLabel,
  'info': instance.info,
  'version': instance.version,
};
