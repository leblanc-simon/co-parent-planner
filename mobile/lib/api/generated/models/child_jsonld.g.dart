// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'child_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ChildJsonld _$ChildJsonldFromJson(Map<String, dynamic> json) => ChildJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  id: json['id'] as String? ?? '',
  firstName: json['firstName'] as String? ?? '',
  color: json['color'] as String? ?? '',
  initial: json['initial'] as String? ?? '',
  version: (json['version'] as num?)?.toInt() ?? 1,
  object5: json['@context'],
  birthDate: json['birthDate'] as String?,
  age: (json['age'] as num?)?.toInt(),
  schoolLabel: json['schoolLabel'] as String?,
  info: (json['info'] as List<dynamic>?)
      ?.map((e) => Map<String, String>.from(e as Map))
      .toList(),
);

Map<String, dynamic> _$ChildJsonldToJson(ChildJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
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
