// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'family_link.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

FamilyLink _$FamilyLinkFromJson(Map<String, dynamic> json) => FamilyLink(
  id: json['id'] as String? ?? '',
  status: json['status'] as String? ?? '',
  otherFamilyId: json['otherFamilyId'] as String? ?? '',
  otherFamilyName: json['otherFamilyName'] as String? ?? '',
  initiatedByMe: json['initiatedByMe'] as bool? ?? false,
);

Map<String, dynamic> _$FamilyLinkToJson(FamilyLink instance) =>
    <String, dynamic>{
      'id': instance.id,
      'status': instance.status,
      'otherFamilyId': instance.otherFamilyId,
      'otherFamilyName': instance.otherFamilyName,
      'initiatedByMe': instance.initiatedByMe,
    };
