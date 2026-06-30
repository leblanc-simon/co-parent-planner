// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'child_create_child_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ChildCreateChildRequest _$ChildCreateChildRequestFromJson(
  Map<String, dynamic> json,
) => ChildCreateChildRequest(
  firstName: json['firstName'] as String?,
  color: json['color'] as String?,
  birthDate: json['birthDate'] as String?,
  schoolLabel: json['schoolLabel'] as String?,
  info: (json['info'] as List<dynamic>?)
      ?.map((e) => Map<String, String?>.from(e as Map))
      .toList(),
);

Map<String, dynamic> _$ChildCreateChildRequestToJson(
  ChildCreateChildRequest instance,
) => <String, dynamic>{
  'firstName': instance.firstName,
  'color': instance.color,
  'birthDate': instance.birthDate,
  'schoolLabel': instance.schoolLabel,
  'info': instance.info,
};
