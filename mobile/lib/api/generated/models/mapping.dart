// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'mapping.g.dart';

@JsonSerializable()
class Mapping {
  const Mapping({
    this.object3,
    this.variable,
    this.property,
    this.requiredValue,
  });
  
  factory Mapping.fromJson(Map<String, Object?> json) => _$MappingFromJson(json);
  
  /// Incorrect name has been replaced. Original name: `@type`.
  @JsonKey(name: '@type')
  final String? object3;
  final String? variable;
  final String? property;

  /// The name has been replaced because it contains a keyword. Original name: `required`.
  @JsonKey(name: 'required')
  final bool? requiredValue;

  Map<String, Object?> toJson() => _$MappingToJson(this);
}
