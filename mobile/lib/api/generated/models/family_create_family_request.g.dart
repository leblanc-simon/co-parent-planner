// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'family_create_family_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

FamilyCreateFamilyRequest _$FamilyCreateFamilyRequestFromJson(
  Map<String, dynamic> json,
) => FamilyCreateFamilyRequest(
  name: json['name'] as String?,
  type: json['type'] == null
      ? null
      : FamilyCreateFamilyRequestType.fromJson(json['type'] as String?),
);

Map<String, dynamic> _$FamilyCreateFamilyRequestToJson(
  FamilyCreateFamilyRequest instance,
) => <String, dynamic>{'name': instance.name, 'type': instance.type};
