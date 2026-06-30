// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'school_holiday_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

SchoolHolidayJsonld _$SchoolHolidayJsonldFromJson(Map<String, dynamic> json) =>
    SchoolHolidayJsonld(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      id: json['id'] as String? ?? '',
      label: json['label'] as String? ?? '',
      startDate: json['startDate'] as String? ?? '',
      endDate: json['endDate'] as String? ?? '',
      source: json['source'] as String? ?? '',
      object5: json['@context'],
      zone: json['zone'] as String?,
      academy: json['academy'] as String?,
      schoolYear: json['schoolYear'] as String?,
      ruleKind: json['ruleKind'] as String?,
      ruleParams: (json['ruleParams'] as Map<String, dynamic>?)?.map(
        (k, e) => MapEntry(k, e as String?),
      ),
    );

Map<String, dynamic> _$SchoolHolidayJsonldToJson(
  SchoolHolidayJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
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
