// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'accept_invitation_request_family_resource.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

AcceptInvitationRequestFamilyResource
_$AcceptInvitationRequestFamilyResourceFromJson(Map<String, dynamic> json) =>
    AcceptInvitationRequestFamilyResource(
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

Map<String, dynamic> _$AcceptInvitationRequestFamilyResourceToJson(
  AcceptInvitationRequestFamilyResource instance,
) => <String, dynamic>{
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
