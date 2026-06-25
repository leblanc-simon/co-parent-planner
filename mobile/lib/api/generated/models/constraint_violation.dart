// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'violations.dart';

part 'constraint_violation.g.dart';

/// Unprocessable entity
@JsonSerializable()
class ConstraintViolation {
  const ConstraintViolation({
    this.violations,
    this.detail,
    this.type,
    this.title,
    this.instance,
    this.status = 422,
  });
  
  factory ConstraintViolation.fromJson(Map<String, Object?> json) => _$ConstraintViolationFromJson(json);
  
  final int status;
  final List<Violations>? violations;
  final String? detail;
  final String? type;
  final String? title;
  final String? instance;

  Map<String, Object?> toJson() => _$ConstraintViolationToJson(this);
}
