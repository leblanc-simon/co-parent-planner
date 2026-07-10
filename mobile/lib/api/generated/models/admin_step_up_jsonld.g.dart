// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'admin_step_up_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

AdminStepUpJsonld _$AdminStepUpJsonldFromJson(Map<String, dynamic> json) =>
    AdminStepUpJsonld(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      token: json['token'] as String? ?? '',
      expiresIn: (json['expiresIn'] as num?)?.toInt() ?? 0,
      object5: json['@context'],
    );

Map<String, dynamic> _$AdminStepUpJsonldToJson(AdminStepUpJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'token': instance.token,
      'expiresIn': instance.expiresIn,
    };
