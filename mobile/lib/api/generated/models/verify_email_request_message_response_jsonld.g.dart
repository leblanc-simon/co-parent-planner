// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'verify_email_request_message_response_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

VerifyEmailRequestMessageResponseJsonld
_$VerifyEmailRequestMessageResponseJsonldFromJson(Map<String, dynamic> json) =>
    VerifyEmailRequestMessageResponseJsonld(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      object5: json['@context'],
      message: json['message'] as String?,
    );

Map<String, dynamic> _$VerifyEmailRequestMessageResponseJsonldToJson(
  VerifyEmailRequestMessageResponseJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'message': instance.message,
};
