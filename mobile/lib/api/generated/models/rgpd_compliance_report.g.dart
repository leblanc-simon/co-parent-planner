// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rgpd_compliance_report.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RgpdComplianceReport _$RgpdComplianceReportFromJson(
  Map<String, dynamic> json,
) => RgpdComplianceReport(
  personalFields: (json['personalFields'] as num?)?.toInt() ?? 0,
  euHosting: json['euHosting'] as bool? ?? true,
  generatedAt: json['generatedAt'] as String? ?? '',
  retentionRules: (json['retentionRules'] as List<dynamic>?)
      ?.map((e) => Map<String, String?>.from(e as Map))
      .toList(),
  gaps: (json['gaps'] as List<dynamic>?)?.map((e) => e as String).toList(),
);

Map<String, dynamic> _$RgpdComplianceReportToJson(
  RgpdComplianceReport instance,
) => <String, dynamic>{
  'retentionRules': instance.retentionRules,
  'gaps': instance.gaps,
  'personalFields': instance.personalFields,
  'euHosting': instance.euHosting,
  'generatedAt': instance.generatedAt,
};
