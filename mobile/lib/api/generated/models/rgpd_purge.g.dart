// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rgpd_purge.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RgpdPurge _$RgpdPurgeFromJson(Map<String, dynamic> json) => RgpdPurge(
  totals: (json['totals'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, (e as num).toInt()),
  ),
  rules: (json['rules'] as List<dynamic>?)
      ?.map((e) => e as Map<String, dynamic>)
      .toList(),
  mode: json['mode'] as String? ?? '',
);

Map<String, dynamic> _$RgpdPurgeToJson(RgpdPurge instance) => <String, dynamic>{
  'mode': instance.mode,
  'totals': instance.totals,
  'rules': instance.rules,
};
