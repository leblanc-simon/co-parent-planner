// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'school_holiday_update_rule_request.g.dart';

/// Période de vacances exposée (US-CUST-3) avec sa règle éventuelle.
@JsonSerializable()
class SchoolHolidayUpdateRuleRequest {
  const SchoolHolidayUpdateRuleRequest({
    this.kind,
    this.params,
  });
  
  factory SchoolHolidayUpdateRuleRequest.fromJson(Map<String, Object?> json) => _$SchoolHolidayUpdateRuleRequestFromJson(json);
  
  final String? kind;
  final Map<String, String?>? params;

  Map<String, Object?> toJson() => _$SchoolHolidayUpdateRuleRequestToJson(this);
}
