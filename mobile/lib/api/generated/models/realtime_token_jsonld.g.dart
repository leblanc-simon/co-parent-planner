// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'realtime_token_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RealtimeTokenJsonld _$RealtimeTokenJsonldFromJson(Map<String, dynamic> json) =>
    RealtimeTokenJsonld(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      object5: json['@context'],
      token: json['token'] as String?,
      hub: json['hub'] as String?,
    );

Map<String, dynamic> _$RealtimeTokenJsonldToJson(
  RealtimeTokenJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'token': instance.token,
  'hub': instance.hub,
};
