// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'school_holiday_update_rule_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

SchoolHolidayUpdateRuleRequest _$SchoolHolidayUpdateRuleRequestFromJson(
  Map<String, dynamic> json,
) => SchoolHolidayUpdateRuleRequest(
  kind: json['kind'] as String?,
  params: (json['params'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as String?),
  ),
);

Map<String, dynamic> _$SchoolHolidayUpdateRuleRequestToJson(
  SchoolHolidayUpdateRuleRequest instance,
) => <String, dynamic>{'kind': instance.kind, 'params': instance.params};
