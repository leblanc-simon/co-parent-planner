// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'school_holiday_create_holiday_request.g.dart';

/// Période de vacances exposée (US-CUST-3) avec sa règle éventuelle.
@JsonSerializable()
class SchoolHolidayCreateHolidayRequest {
  const SchoolHolidayCreateHolidayRequest({
    required this.label,
    required this.startDate,
    required this.endDate,
  });
  
  factory SchoolHolidayCreateHolidayRequest.fromJson(Map<String, Object?> json) => _$SchoolHolidayCreateHolidayRequestFromJson(json);
  
  final String? label;
  final String? startDate;
  final String? endDate;

  Map<String, Object?> toJson() => _$SchoolHolidayCreateHolidayRequestToJson(this);
}
