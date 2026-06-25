// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'hydra_item_base_schema.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

HydraItemBaseSchema _$HydraItemBaseSchemaFromJson(Map<String, dynamic> json) =>
    HydraItemBaseSchema(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      object5: json['@context'],
    );

Map<String, dynamic> _$HydraItemBaseSchemaToJson(
  HydraItemBaseSchema instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
};
