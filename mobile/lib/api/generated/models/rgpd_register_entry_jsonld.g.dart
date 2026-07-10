// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rgpd_register_entry_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RgpdRegisterEntryJsonld _$RgpdRegisterEntryJsonldFromJson(
  Map<String, dynamic> json,
) => RgpdRegisterEntryJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  entity: json['entity'] as String? ?? '',
  field: json['field'] as String? ?? '',
  category: json['category'] as String? ?? '',
  encrypted: json['encrypted'] as bool? ?? false,
  legalBasis: json['legalBasis'] as String? ?? '',
  purpose: json['purpose'] as String? ?? '',
  retention: json['retention'] as String? ?? '',
  recipients: json['recipients'] as String? ?? '',
  object5: json['@context'],
);

Map<String, dynamic> _$RgpdRegisterEntryJsonldToJson(
  RgpdRegisterEntryJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'entity': instance.entity,
  'field': instance.field,
  'category': instance.category,
  'encrypted': instance.encrypted,
  'legalBasis': instance.legalBasis,
  'purpose': instance.purpose,
  'retention': instance.retention,
  'recipients': instance.recipients,
};
