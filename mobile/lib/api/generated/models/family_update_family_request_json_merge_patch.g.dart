// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'family_update_family_request_json_merge_patch.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

FamilyUpdateFamilyRequestJsonMergePatch
_$FamilyUpdateFamilyRequestJsonMergePatchFromJson(Map<String, dynamic> json) =>
    FamilyUpdateFamilyRequestJsonMergePatch(
      name: json['name'] as String?,
      type: json['type'] == null
          ? null
          : FamilyUpdateFamilyRequestJsonMergePatchType.fromJson(
              json['type'] as String?,
            ),
      timezone: json['timezone'] as String?,
      schoolZone: json['schoolZone'] == null
          ? null
          : FamilyUpdateFamilyRequestJsonMergePatchSchoolZone.fromJson(
              json['schoolZone'] as String?,
            ),
      academy: json['academy'] as String?,
      expensesEnabled: json['expensesEnabled'] as bool?,
      handoverWeekday: (json['handoverWeekday'] as num?)?.toInt(),
      handoverTime: json['handoverTime'] as String?,
    );

Map<String, dynamic> _$FamilyUpdateFamilyRequestJsonMergePatchToJson(
  FamilyUpdateFamilyRequestJsonMergePatch instance,
) => <String, dynamic>{
  'name': instance.name,
  'type': instance.type,
  'timezone': instance.timezone,
  'schoolZone': instance.schoolZone,
  'academy': instance.academy,
  'expensesEnabled': instance.expensesEnabled,
  'handoverWeekday': instance.handoverWeekday,
  'handoverTime': instance.handoverTime,
};
