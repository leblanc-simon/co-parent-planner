// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'search.dart';
import 'view.dart';

part 'hydra_collection_base_schema.g.dart';

@JsonSerializable()
class HydraCollectionBaseSchema {
  const HydraCollectionBaseSchema({
    this.totalItems,
    this.search,
    this.view,
  });
  
  factory HydraCollectionBaseSchema.fromJson(Map<String, Object?> json) => _$HydraCollectionBaseSchemaFromJson(json);
  
  final int? totalItems;
  final Search? search;
  final View? view;

  Map<String, Object?> toJson() => _$HydraCollectionBaseSchemaToJson(this);
}
