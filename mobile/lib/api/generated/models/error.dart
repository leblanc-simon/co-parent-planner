// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'error.g.dart';

/// A representation of common errors.
@JsonSerializable()
class Error {
  const Error({
    this.status = 400,
    this.title,
    this.detail,
    this.instance,
    this.type,
  });
  
  factory Error.fromJson(Map<String, Object?> json) => _$ErrorFromJson(json);
  
  /// A short, human-readable summary of the problem.
  final String? title;

  /// A human-readable explanation specific to this occurrence of the problem.
  final String? detail;
  final int? status;

  /// A URI reference that identifies the specific occurrence of the problem. It may or may not yield further information if dereferenced.
  final String? instance;

  /// A URI reference that identifies the problem type
  final String? type;

  Map<String, Object?> toJson() => _$ErrorToJson(this);
}
