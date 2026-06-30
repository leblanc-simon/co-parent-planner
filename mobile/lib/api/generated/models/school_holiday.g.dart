// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'school_holiday.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

SchoolHoliday _$SchoolHolidayFromJson(Map<String, dynamic> json) =>
    SchoolHoliday(
      zone: json['zone'] as String?,
      academy: json['academy'] as String?,
      schoolYear: json['schoolYear'] as String?,
      ruleKind: json['ruleKind'] as String?,
      ruleParams: (json['ruleParams'] as Map<String, dynamic>?)?.map(
        (k, e) => MapEntry(k, e as String?),
      ),
      id: json['id'] as String? ?? '',
      label: json['label'] as String? ?? '',
      startDate: json['startDate'] as String? ?? '',
      endDate: json['endDate'] as String? ?? '',
      source: json['source'] as String? ?? '',
    );

Map<String, dynamic> _$SchoolHolidayToJson(SchoolHoliday instance) =>
    <String, dynamic>{
      'id': instance.id,
      'label': instance.label,
      'startDate': instance.startDate,
      'endDate': instance.endDate,
      'zone': instance.zone,
      'academy': instance.academy,
      'schoolYear': instance.schoolYear,
      'source': instance.source,
      'ruleKind': instance.ruleKind,
      'ruleParams': instance.ruleParams,
    };
