// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'school_holiday_create_holiday_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

SchoolHolidayCreateHolidayRequest _$SchoolHolidayCreateHolidayRequestFromJson(
  Map<String, dynamic> json,
) => SchoolHolidayCreateHolidayRequest(
  label: json['label'] as String?,
  startDate: json['startDate'] as String?,
  endDate: json['endDate'] as String?,
);

Map<String, dynamic> _$SchoolHolidayCreateHolidayRequestToJson(
  SchoolHolidayCreateHolidayRequest instance,
) => <String, dynamic>{
  'label': instance.label,
  'startDate': instance.startDate,
  'endDate': instance.endDate,
};
