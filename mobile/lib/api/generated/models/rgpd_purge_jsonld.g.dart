// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rgpd_purge_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RgpdPurgeJsonld _$RgpdPurgeJsonldFromJson(Map<String, dynamic> json) =>
    RgpdPurgeJsonld(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      mode: json['mode'] as String? ?? '',
      object5: json['@context'],
      totals: (json['totals'] as Map<String, dynamic>?)?.map(
        (k, e) => MapEntry(k, (e as num).toInt()),
      ),
      rules: (json['rules'] as List<dynamic>?)
          ?.map((e) => e as Map<String, dynamic>)
          .toList(),
    );

Map<String, dynamic> _$RgpdPurgeJsonldToJson(RgpdPurgeJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'mode': instance.mode,
      'totals': instance.totals,
      'rules': instance.rules,
    };
