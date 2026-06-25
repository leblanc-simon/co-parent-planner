// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'error_jsonld.g.dart';

/// A representation of common errors.
@JsonSerializable()
class ErrorJsonld {
  const ErrorJsonld({
    required this.object6,
    required this.object7,
    this.status = 400,
    this.object5,
    this.title,
    this.detail,
    this.instance,
    this.type,
    this.description,
  });
  
  factory ErrorJsonld.fromJson(Map<String, Object?> json) => _$ErrorJsonldFromJson(json);
  
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

  /// A short, human-readable summary of the problem.
  final String? title;

  /// A human-readable explanation specific to this occurrence of the problem.
  final String? detail;
  final int? status;

  /// A URI reference that identifies the specific occurrence of the problem. It may or may not yield further information if dereferenced.
  final String? instance;

  /// A URI reference that identifies the problem type
  final String? type;
  final String? description;

  Map<String, Object?> toJson() => _$ErrorJsonldToJson(this);
}
