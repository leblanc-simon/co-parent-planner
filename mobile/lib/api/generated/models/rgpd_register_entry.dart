// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rgpd_register_entry.g.dart';

/// Une entrée du registre des traitements (US-RGPD-1), synthétisée depuis les attributs `#[PersonalData]`.
/// `GET /admin/rgpd/register` (admin technique + step-up).
@JsonSerializable()
class RgpdRegisterEntry {
  const RgpdRegisterEntry({
    this.entity = '',
    this.field = '',
    this.category = '',
    this.encrypted = false,
    this.legalBasis = '',
    this.purpose = '',
    this.retention = '',
    this.recipients = '',
  });
  
  factory RgpdRegisterEntry.fromJson(Map<String, Object?> json) => _$RgpdRegisterEntryFromJson(json);
  
  final String entity;
  final String field;
  final String category;
  final bool encrypted;
  final String legalBasis;
  final String purpose;
  final String retention;
  final String recipients;

  Map<String, Object?> toJson() => _$RgpdRegisterEntryToJson(this);
}
