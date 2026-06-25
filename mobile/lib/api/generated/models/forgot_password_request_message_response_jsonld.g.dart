// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'forgot_password_request_message_response_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ForgotPasswordRequestMessageResponseJsonld
_$ForgotPasswordRequestMessageResponseJsonldFromJson(
  Map<String, dynamic> json,
) => ForgotPasswordRequestMessageResponseJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  object5: json['@context'],
  message: json['message'] as String?,
);

Map<String, dynamic> _$ForgotPasswordRequestMessageResponseJsonldToJson(
  ForgotPasswordRequestMessageResponseJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'message': instance.message,
};
