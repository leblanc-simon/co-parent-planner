// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'child_update_child_request_json_merge_patch.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ChildUpdateChildRequestJsonMergePatch
_$ChildUpdateChildRequestJsonMergePatchFromJson(Map<String, dynamic> json) =>
    ChildUpdateChildRequestJsonMergePatch(
      firstName: json['firstName'] as String?,
      color: json['color'] as String?,
      birthDate: json['birthDate'] as String?,
      schoolLabel: json['schoolLabel'] as String?,
      info: (json['info'] as List<dynamic>?)
          ?.map((e) => Map<String, String?>.from(e as Map))
          .toList(),
      expectedVersion: (json['expectedVersion'] as num?)?.toInt(),
    );

Map<String, dynamic> _$ChildUpdateChildRequestJsonMergePatchToJson(
  ChildUpdateChildRequestJsonMergePatch instance,
) => <String, dynamic>{
  'firstName': instance.firstName,
  'color': instance.color,
  'birthDate': instance.birthDate,
  'schoolLabel': instance.schoolLabel,
  'info': instance.info,
  'expectedVersion': instance.expectedVersion,
};
