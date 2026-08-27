// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'custody_stats.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CustodyStats _$CustodyStatsFromJson(Map<String, dynamic> json) => CustodyStats(
  from: json['from'] as String?,
  to: json['to'] as String?,
  id: json['id'] as String? ?? 'stats',
  parentACount: (json['parentACount'] as num?)?.toInt() ?? 0,
  parentBCount: (json['parentBCount'] as num?)?.toInt() ?? 0,
  otherCount: (json['otherCount'] as num?)?.toInt() ?? 0,
  total: (json['total'] as num?)?.toInt() ?? 0,
  daysInWindow: (json['daysInWindow'] as num?)?.toInt() ?? 0,
  pctA: json['pctA'] as num? ?? 0,
  pctB: json['pctB'] as num? ?? 0,
);

Map<String, dynamic> _$CustodyStatsToJson(CustodyStats instance) =>
    <String, dynamic>{
      'id': instance.id,
      'parentACount': instance.parentACount,
      'parentBCount': instance.parentBCount,
      'otherCount': instance.otherCount,
      'total': instance.total,
      'daysInWindow': instance.daysInWindow,
      'pctA': instance.pctA,
      'pctB': instance.pctB,
      'from': instance.from,
      'to': instance.to,
    };
