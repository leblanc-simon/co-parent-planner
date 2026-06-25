// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'hydra_collection_base_schema_no_pagination.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

HydraCollectionBaseSchemaNoPagination
_$HydraCollectionBaseSchemaNoPaginationFromJson(Map<String, dynamic> json) =>
    HydraCollectionBaseSchemaNoPagination(
      totalItems: (json['totalItems'] as num?)?.toInt(),
      search: json['search'] == null
          ? null
          : Search.fromJson(json['search'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$HydraCollectionBaseSchemaNoPaginationToJson(
  HydraCollectionBaseSchemaNoPagination instance,
) => <String, dynamic>{
  'totalItems': instance.totalItems,
  'search': instance.search,
};
