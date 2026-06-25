// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'accept_invitation_request_family_resource_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

AcceptInvitationRequestFamilyResourceJsonld
_$AcceptInvitationRequestFamilyResourceJsonldFromJson(
  Map<String, dynamic> json,
) => AcceptInvitationRequestFamilyResourceJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  id: json['id'] as String? ?? '',
  name: json['name'] as String? ?? '',
  type: json['type'] as String? ?? '',
  timezone: json['timezone'] as String? ?? '',
  expensesEnabled: json['expensesEnabled'] as bool? ?? false,
  hasCustody: json['hasCustody'] as bool? ?? false,
  role: json['role'] as String? ?? '',
  isAdmin: json['isAdmin'] as bool? ?? false,
  object5: json['@context'],
  schoolZone: json['schoolZone'] as String?,
  academy: json['academy'] as String?,
);

Map<String, dynamic> _$AcceptInvitationRequestFamilyResourceJsonldToJson(
  AcceptInvitationRequestFamilyResourceJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
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
