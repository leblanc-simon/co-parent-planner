// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'mapping.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Mapping _$MappingFromJson(Map<String, dynamic> json) => Mapping(
  object3: json['@type'] as String?,
  variable: json['variable'] as String?,
  property: json['property'] as String?,
  requiredValue: json['required'] as bool?,
);

Map<String, dynamic> _$MappingToJson(Mapping instance) => <String, dynamic>{
  '@type': instance.object3,
  'variable': instance.variable,
  'property': instance.property,
  'required': instance.requiredValue,
};
