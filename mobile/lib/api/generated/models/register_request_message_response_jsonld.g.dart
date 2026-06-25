// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'register_request_message_response_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RegisterRequestMessageResponseJsonld
_$RegisterRequestMessageResponseJsonldFromJson(Map<String, dynamic> json) =>
    RegisterRequestMessageResponseJsonld(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      object5: json['@context'],
      message: json['message'] as String?,
    );

Map<String, dynamic> _$RegisterRequestMessageResponseJsonldToJson(
  RegisterRequestMessageResponseJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'message': instance.message,
};
