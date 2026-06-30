// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'school_holiday.g.dart';

/// Période de vacances exposée (US-CUST-3) avec sa règle éventuelle.
@JsonSerializable()
class SchoolHoliday {
  const SchoolHoliday({
    this.zone,
    this.academy,
    this.schoolYear,
    this.ruleKind,
    this.ruleParams,
    this.id = '',
    this.label = '',
    this.startDate = '',
    this.endDate = '',
    this.source = '',
  });
  
  factory SchoolHoliday.fromJson(Map<String, Object?> json) => _$SchoolHolidayFromJson(json);
  
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

  Map<String, Object?> toJson() => _$SchoolHolidayToJson(this);
}
