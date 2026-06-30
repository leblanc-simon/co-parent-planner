// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'family_link_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

FamilyLinkJsonld _$FamilyLinkJsonldFromJson(Map<String, dynamic> json) =>
    FamilyLinkJsonld(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      id: json['id'] as String? ?? '',
      status: json['status'] as String? ?? '',
      otherFamilyId: json['otherFamilyId'] as String? ?? '',
      otherFamilyName: json['otherFamilyName'] as String? ?? '',
      initiatedByMe: json['initiatedByMe'] as bool? ?? false,
      object5: json['@context'],
    );

Map<String, dynamic> _$FamilyLinkJsonldToJson(FamilyLinkJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'id': instance.id,
      'status': instance.status,
      'otherFamilyId': instance.otherFamilyId,
      'otherFamilyName': instance.otherFamilyName,
      'initiatedByMe': instance.initiatedByMe,
    };
