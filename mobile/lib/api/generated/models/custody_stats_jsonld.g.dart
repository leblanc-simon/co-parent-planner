// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'custody_stats_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CustodyStatsJsonld _$CustodyStatsJsonldFromJson(Map<String, dynamic> json) =>
    CustodyStatsJsonld(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      id: json['id'] as String? ?? 'stats',
      parentACount: (json['parentACount'] as num?)?.toInt() ?? 0,
      parentBCount: (json['parentBCount'] as num?)?.toInt() ?? 0,
      otherCount: (json['otherCount'] as num?)?.toInt() ?? 0,
      total: (json['total'] as num?)?.toInt() ?? 0,
      pctA: json['pctA'] as num? ?? 0,
      pctB: json['pctB'] as num? ?? 0,
      object5: json['@context'],
      from: json['from'] as String?,
      to: json['to'] as String?,
    );

Map<String, dynamic> _$CustodyStatsJsonldToJson(CustodyStatsJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'id': instance.id,
      'parentACount': instance.parentACount,
      'parentBCount': instance.parentBCount,
      'otherCount': instance.otherCount,
      'total': instance.total,
      'pctA': instance.pctA,
      'pctB': instance.pctB,
      'from': instance.from,
      'to': instance.to,
    };
