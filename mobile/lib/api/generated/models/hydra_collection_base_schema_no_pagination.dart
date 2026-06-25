// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'search.dart';

part 'hydra_collection_base_schema_no_pagination.g.dart';

@JsonSerializable()
class HydraCollectionBaseSchemaNoPagination {
  const HydraCollectionBaseSchemaNoPagination({
    this.totalItems,
    this.search,
  });
  
  factory HydraCollectionBaseSchemaNoPagination.fromJson(Map<String, Object?> json) => _$HydraCollectionBaseSchemaNoPaginationFromJson(json);
  
  final int? totalItems;
  final Search? search;

  Map<String, Object?> toJson() => _$HydraCollectionBaseSchemaNoPaginationToJson(this);
}
