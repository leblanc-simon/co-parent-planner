// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'hydra_collection_base_schema.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

HydraCollectionBaseSchema _$HydraCollectionBaseSchemaFromJson(
  Map<String, dynamic> json,
) => HydraCollectionBaseSchema(
  totalItems: (json['totalItems'] as num?)?.toInt(),
  search: json['search'] == null
      ? null
      : Search.fromJson(json['search'] as Map<String, dynamic>),
  view: json['view'] == null
      ? null
      : View.fromJson(json['view'] as Map<String, dynamic>),
);

Map<String, dynamic> _$HydraCollectionBaseSchemaToJson(
  HydraCollectionBaseSchema instance,
) => <String, dynamic>{
  'totalItems': instance.totalItems,
  'search': instance.search,
  'view': instance.view,
};
