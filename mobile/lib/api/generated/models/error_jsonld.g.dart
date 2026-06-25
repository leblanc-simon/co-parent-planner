// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'error_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ErrorJsonld _$ErrorJsonldFromJson(Map<String, dynamic> json) => ErrorJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  status: (json['status'] as num?)?.toInt() ?? 400,
  object5: json['@context'],
  title: json['title'] as String?,
  detail: json['detail'] as String?,
  instance: json['instance'] as String?,
  type: json['type'] as String?,
  description: json['description'] as String?,
);

Map<String, dynamic> _$ErrorJsonldToJson(ErrorJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'title': instance.title,
      'detail': instance.detail,
      'status': instance.status,
      'instance': instance.instance,
      'type': instance.type,
      'description': instance.description,
    };
