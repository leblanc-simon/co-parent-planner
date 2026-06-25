// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'violations.g.dart';

@JsonSerializable()
class Violations {
  const Violations({
    required this.propertyPath,
    required this.message,
    this.code,
    this.hint,
    this.payload,
  });
  
  factory Violations.fromJson(Map<String, Object?> json) => _$ViolationsFromJson(json);
  
  /// The property path of the violation
  final String propertyPath;

  /// The message associated with the violation
  final String message;

  /// The code of the violation
  final String? code;

  /// An extra hint to understand the violation
  final String? hint;

  /// The serialized payload of the violation
  final dynamic payload;

  Map<String, Object?> toJson() => _$ViolationsToJson(this);
}
