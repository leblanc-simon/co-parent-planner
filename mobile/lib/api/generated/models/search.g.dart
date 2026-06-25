// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'search.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Search _$SearchFromJson(Map<String, dynamic> json) => Search(
  object2: json['@type'] as String?,
  template: json['template'] as String?,
  variableRepresentation: json['variableRepresentation'] as String?,
  mapping: (json['mapping'] as List<dynamic>?)
      ?.map((e) => Mapping.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$SearchToJson(Search instance) => <String, dynamic>{
  '@type': instance.object2,
  'template': instance.template,
  'variableRepresentation': instance.variableRepresentation,
  'mapping': instance.mapping,
};
