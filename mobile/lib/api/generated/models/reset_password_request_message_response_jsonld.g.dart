// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'reset_password_request_message_response_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ResetPasswordRequestMessageResponseJsonld
_$ResetPasswordRequestMessageResponseJsonldFromJson(
  Map<String, dynamic> json,
) => ResetPasswordRequestMessageResponseJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  object5: json['@context'],
  message: json['message'] as String?,
);

Map<String, dynamic> _$ResetPasswordRequestMessageResponseJsonldToJson(
  ResetPasswordRequestMessageResponseJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'message': instance.message,
};
