// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rgpd_compliance_report_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RgpdComplianceReportJsonld _$RgpdComplianceReportJsonldFromJson(
  Map<String, dynamic> json,
) => RgpdComplianceReportJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  personalFields: (json['personalFields'] as num?)?.toInt() ?? 0,
  euHosting: json['euHosting'] as bool? ?? true,
  generatedAt: json['generatedAt'] as String? ?? '',
  object5: json['@context'],
  retentionRules: (json['retentionRules'] as List<dynamic>?)
      ?.map((e) => Map<String, String?>.from(e as Map))
      .toList(),
  gaps: (json['gaps'] as List<dynamic>?)?.map((e) => e as String).toList(),
);

Map<String, dynamic> _$RgpdComplianceReportJsonldToJson(
  RgpdComplianceReportJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'retentionRules': instance.retentionRules,
  'gaps': instance.gaps,
  'personalFields': instance.personalFields,
  'euHosting': instance.euHosting,
  'generatedAt': instance.generatedAt,
};
