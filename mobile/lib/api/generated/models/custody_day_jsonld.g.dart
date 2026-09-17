// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'custody_day_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CustodyDayJsonld _$CustodyDayJsonldFromJson(Map<String, dynamic> json) =>
    CustodyDayJsonld(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      date: json['date'] as String? ?? '',
      source: json['source'] as String? ?? '',
      object5: json['@context'],
      parentMembershipId: json['parentMembershipId'] as String?,
      segments: (json['segments'] as List<dynamic>?)
          ?.map((e) => Map<String, String?>.from(e as Map))
          .toList(),
    );

Map<String, dynamic> _$CustodyDayJsonldToJson(CustodyDayJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'date': instance.date,
      'parentMembershipId': instance.parentMembershipId,
      'source': instance.source,
      'segments': instance.segments,
    };
