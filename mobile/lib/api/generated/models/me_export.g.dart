// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'me_export.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

MeExport _$MeExportFromJson(Map<String, dynamic> json) => MeExport(
  downloadUrl: json['downloadUrl'] as String?,
  id: json['id'] as String? ?? '',
  status: json['status'] as String? ?? '',
);

Map<String, dynamic> _$MeExportToJson(MeExport instance) => <String, dynamic>{
  'id': instance.id,
  'status': instance.status,
  'downloadUrl': instance.downloadUrl,
};
