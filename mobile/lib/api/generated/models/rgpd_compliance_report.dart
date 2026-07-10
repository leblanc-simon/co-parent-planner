// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rgpd_compliance_report.g.dart';

/// Rapport d'état de conformité (US-RGPD-4) : rétentions en vigueur, écarts détectés, hébergement UE.
/// `GET /admin/rgpd/compliance-report` (admin technique + step-up).
@JsonSerializable()
class RgpdComplianceReport {
  const RgpdComplianceReport({
    this.personalFields = 0,
    this.euHosting = true,
    this.generatedAt = '',
    this.retentionRules,
    this.gaps,
  });
  
  factory RgpdComplianceReport.fromJson(Map<String, Object?> json) => _$RgpdComplianceReportFromJson(json);
  
  final List<Map<String, String?>>? retentionRules;

  /// écarts (ex. champ chiffré sans entrée de registre)
  final List<String>? gaps;
  final int personalFields;
  final bool euHosting;
  final String generatedAt;

  Map<String, Object?> toJson() => _$RgpdComplianceReportToJson(this);
}
