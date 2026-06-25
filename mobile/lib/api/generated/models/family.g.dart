// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'family.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Family _$FamilyFromJson(Map<String, dynamic> json) => Family(
  schoolZone: json['schoolZone'] as String?,
  academy: json['academy'] as String?,
  id: json['id'] as String? ?? '',
  name: json['name'] as String? ?? '',
  type: json['type'] as String? ?? '',
  timezone: json['timezone'] as String? ?? '',
  expensesEnabled: json['expensesEnabled'] as bool? ?? false,
  hasCustody: json['hasCustody'] as bool? ?? false,
  role: json['role'] as String? ?? '',
  isAdmin: json['isAdmin'] as bool? ?? false,
);

Map<String, dynamic> _$FamilyToJson(Family instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'type': instance.type,
  'timezone': instance.timezone,
  'schoolZone': instance.schoolZone,
  'academy': instance.academy,
  'expensesEnabled': instance.expensesEnabled,
  'hasCustody': instance.hasCustody,
  'role': instance.role,
  'isAdmin': instance.isAdmin,
};
