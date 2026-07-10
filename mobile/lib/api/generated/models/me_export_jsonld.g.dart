// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'me_export_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

MeExportJsonld _$MeExportJsonldFromJson(Map<String, dynamic> json) =>
    MeExportJsonld(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      id: json['id'] as String? ?? '',
      status: json['status'] as String? ?? '',
      object5: json['@context'],
      downloadUrl: json['downloadUrl'] as String?,
    );

Map<String, dynamic> _$MeExportJsonldToJson(MeExportJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'id': instance.id,
      'status': instance.status,
      'downloadUrl': instance.downloadUrl,
    };
