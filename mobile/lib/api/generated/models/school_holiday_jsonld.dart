// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'school_holiday_jsonld.g.dart';

/// Période de vacances exposée (US-CUST-3) avec sa règle éventuelle.
@JsonSerializable()
class SchoolHolidayJsonld {
  const SchoolHolidayJsonld({
    required this.object6,
    required this.object7,
    this.id = '',
    this.label = '',
    this.startDate = '',
    this.endDate = '',
    this.source = '',
    this.object5,
    this.zone,
    this.academy,
    this.schoolYear,
    this.ruleKind,
    this.ruleParams,
  });
  
  factory SchoolHolidayJsonld.fromJson(Map<String, Object?> json) => _$SchoolHolidayJsonldFromJson(json);
  
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
  final String id;
  final String label;
  final String startDate;
  final String endDate;
  final String? zone;
  final String? academy;
  final String? schoolYear;
  final String source;
  final String? ruleKind;
  final Map<String, String?>? ruleParams;

  Map<String, Object?> toJson() => _$SchoolHolidayJsonldToJson(this);
}
