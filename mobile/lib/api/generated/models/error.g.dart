// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'error.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Error _$ErrorFromJson(Map<String, dynamic> json) => Error(
  status: (json['status'] as num?)?.toInt() ?? 400,
  title: json['title'] as String?,
  detail: json['detail'] as String?,
  instance: json['instance'] as String?,
  type: json['type'] as String?,
);

Map<String, dynamic> _$ErrorToJson(Error instance) => <String, dynamic>{
  'title': instance.title,
  'detail': instance.detail,
  'status': instance.status,
  'instance': instance.instance,
  'type': instance.type,
};
