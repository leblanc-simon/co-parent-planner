// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'custody_color.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CustodyColor _$CustodyColorFromJson(Map<String, dynamic> json) => CustodyColor(
  targetMembershipId: json['targetMembershipId'] as String? ?? '',
  palette: json['palette'] as String? ?? '',
  border: json['border'] as String? ?? '',
  soft: json['soft'] as String? ?? '',
  ink: json['ink'] as String? ?? '',
);

Map<String, dynamic> _$CustodyColorToJson(CustodyColor instance) =>
    <String, dynamic>{
      'targetMembershipId': instance.targetMembershipId,
      'palette': instance.palette,
      'border': instance.border,
      'soft': instance.soft,
      'ink': instance.ink,
    };
