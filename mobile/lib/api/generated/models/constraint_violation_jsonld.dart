// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'violations2.dart';

part 'constraint_violation_jsonld.g.dart';

/// Unprocessable entity
@JsonSerializable()
class ConstraintViolationJsonld {
  const ConstraintViolationJsonld({
    required this.object6,
    required this.object7,
    this.status = 422,
    this.object5,
    this.violations,
    this.detail,
    this.description,
    this.type,
    this.title,
    this.instance,
  });
  
  factory ConstraintViolationJsonld.fromJson(Map<String, Object?> json) => _$ConstraintViolationJsonldFromJson(json);
  
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
  final int status;
  final List<Violations2>? violations;
  final String? detail;
  final String? description;
  final String? type;
  final String? title;
  final String? instance;

  Map<String, Object?> toJson() => _$ConstraintViolationJsonldToJson(this);
}
