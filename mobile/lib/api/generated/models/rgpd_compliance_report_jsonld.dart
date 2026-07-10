// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rgpd_compliance_report_jsonld.g.dart';

/// Rapport d'état de conformité (US-RGPD-4) : rétentions en vigueur, écarts détectés, hébergement UE.
/// `GET /admin/rgpd/compliance-report` (admin technique + step-up).
@JsonSerializable()
class RgpdComplianceReportJsonld {
  const RgpdComplianceReportJsonld({
    required this.object6,
    required this.object7,
    this.personalFields = 0,
    this.euHosting = true,
    this.generatedAt = '',
    this.object5,
    this.retentionRules,
    this.gaps,
  });
  
  factory RgpdComplianceReportJsonld.fromJson(Map<String, Object?> json) => _$RgpdComplianceReportJsonldFromJson(json);
  
  /// Incorrect name has been replaced. Original name: `@context`.
  /// Incorrect name has been replaced. Original name: `@context`.
  @JsonKey(name: '@context')
  final dynamic object5;

  /// Incorrect name has been replaced. Original name: `@id`.
  @JsonKey(name: '@id')
  final String object6;

  /// Incorrect name has been replaced. Original name: `@type`.
  @JsonKey(name: '@type')
  final String object7;
  final List<Map<String, String?>>? retentionRules;

  /// écarts (ex. champ chiffré sans entrée de registre)
  final List<String>? gaps;
  final int personalFields;
  final bool euHosting;
  final String generatedAt;

  Map<String, Object?> toJson() => _$RgpdComplianceReportJsonldToJson(this);
}
