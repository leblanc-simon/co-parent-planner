// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'custody_day.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CustodyDay _$CustodyDayFromJson(Map<String, dynamic> json) => CustodyDay(
  parentMembershipId: json['parentMembershipId'] as String?,
  segments: (json['segments'] as List<dynamic>?)
      ?.map((e) => Map<String, String?>.from(e as Map))
      .toList(),
  date: json['date'] as String? ?? '',
  source: json['source'] as String? ?? '',
);

Map<String, dynamic> _$CustodyDayToJson(CustodyDay instance) =>
    <String, dynamic>{
      'date': instance.date,
      'parentMembershipId': instance.parentMembershipId,
      'source': instance.source,
      'segments': instance.segments,
    };
