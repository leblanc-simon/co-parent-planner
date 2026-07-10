// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rgpd_register_entry.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RgpdRegisterEntry _$RgpdRegisterEntryFromJson(Map<String, dynamic> json) =>
    RgpdRegisterEntry(
      entity: json['entity'] as String? ?? '',
      field: json['field'] as String? ?? '',
      category: json['category'] as String? ?? '',
      encrypted: json['encrypted'] as bool? ?? false,
      legalBasis: json['legalBasis'] as String? ?? '',
      purpose: json['purpose'] as String? ?? '',
      retention: json['retention'] as String? ?? '',
      recipients: json['recipients'] as String? ?? '',
    );

Map<String, dynamic> _$RgpdRegisterEntryToJson(RgpdRegisterEntry instance) =>
    <String, dynamic>{
      'entity': instance.entity,
      'field': instance.field,
      'category': instance.category,
      'encrypted': instance.encrypted,
      'legalBasis': instance.legalBasis,
      'purpose': instance.purpose,
      'retention': instance.retention,
      'recipients': instance.recipients,
    };
