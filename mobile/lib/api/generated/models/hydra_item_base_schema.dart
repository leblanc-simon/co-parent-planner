// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hydra_item_base_schema.g.dart';

@JsonSerializable()
class HydraItemBaseSchema {
  const HydraItemBaseSchema({
    required this.object6,
    required this.object7,
    this.object5,
  });
  
  factory HydraItemBaseSchema.fromJson(Map<String, Object?> json) => _$HydraItemBaseSchemaFromJson(json);
  
  /// Incorrect name has been replaced. Original name: `@context`.
  /// Incorrect name has been replaced. Original name: `@context`.
  @JsonKey(name: '@context')
  final dynamic object5;

  /// Incorrect name has been replaced. Original name: `@id`.
  @JsonKey(name: '@id')
  final String object6;

  /// Incorrect name has been replaced. Original name: `@type`.
  @JsonKey(name: '@type')
  final String object7;

  Map<String, Object?> toJson() => _$HydraItemBaseSchemaToJson(this);
}
