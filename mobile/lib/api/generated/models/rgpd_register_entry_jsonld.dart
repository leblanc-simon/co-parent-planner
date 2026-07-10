// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rgpd_register_entry_jsonld.g.dart';

/// Une entrée du registre des traitements (US-RGPD-1), synthétisée depuis les attributs `#[PersonalData]`.
/// `GET /admin/rgpd/register` (admin technique + step-up).
@JsonSerializable()
class RgpdRegisterEntryJsonld {
  const RgpdRegisterEntryJsonld({
    required this.object6,
    required this.object7,
    this.entity = '',
    this.field = '',
    this.category = '',
    this.encrypted = false,
    this.legalBasis = '',
    this.purpose = '',
    this.retention = '',
    this.recipients = '',
    this.object5,
  });
  
  factory RgpdRegisterEntryJsonld.fromJson(Map<String, Object?> json) => _$RgpdRegisterEntryJsonldFromJson(json);
  
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
  final String entity;
  final String field;
  final String category;
  final bool encrypted;
  final String legalBasis;
  final String purpose;
  final String retention;
  final String recipients;

  Map<String, Object?> toJson() => _$RgpdRegisterEntryJsonldToJson(this);
}
