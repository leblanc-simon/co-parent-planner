// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'mapping.dart';

part 'search.g.dart';

@JsonSerializable()
class Search {
  const Search({
    this.object2,
    this.template,
    this.variableRepresentation,
    this.mapping,
  });
  
  factory Search.fromJson(Map<String, Object?> json) => _$SearchFromJson(json);
  
  /// Incorrect name has been replaced. Original name: `@type`.
  @JsonKey(name: '@type')
  final String? object2;
  final String? template;
  final String? variableRepresentation;
  final List<Mapping>? mapping;

  Map<String, Object?> toJson() => _$SearchToJson(this);
}
