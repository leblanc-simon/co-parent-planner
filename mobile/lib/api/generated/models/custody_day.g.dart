// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'custody_day.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CustodyDay _$CustodyDayFromJson(Map<String, dynamic> json) => CustodyDay(
  parentMembershipId: json['parentMembershipId'] as String?,
  date: json['date'] as String? ?? '',
  source: json['source'] as String? ?? '',
);

Map<String, dynamic> _$CustodyDayToJson(CustodyDay instance) =>
    <String, dynamic>{
      'date': instance.date,
      'parentMembershipId': instance.parentMembershipId,
      'source': instance.source,
    };
