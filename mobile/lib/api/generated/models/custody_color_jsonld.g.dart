// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'custody_color_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CustodyColorJsonld _$CustodyColorJsonldFromJson(Map<String, dynamic> json) =>
    CustodyColorJsonld(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      targetMembershipId: json['targetMembershipId'] as String? ?? '',
      palette: json['palette'] as String? ?? '',
      border: json['border'] as String? ?? '',
      soft: json['soft'] as String? ?? '',
      ink: json['ink'] as String? ?? '',
      object5: json['@context'],
    );

Map<String, dynamic> _$CustodyColorJsonldToJson(CustodyColorJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'targetMembershipId': instance.targetMembershipId,
      'palette': instance.palette,
      'border': instance.border,
      'soft': instance.soft,
      'ink': instance.ink,
    };
