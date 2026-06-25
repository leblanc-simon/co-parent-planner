// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'logout_request_message_response_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

LogoutRequestMessageResponseJsonld _$LogoutRequestMessageResponseJsonldFromJson(
  Map<String, dynamic> json,
) => LogoutRequestMessageResponseJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  object5: json['@context'],
  message: json['message'] as String?,
);

Map<String, dynamic> _$LogoutRequestMessageResponseJsonldToJson(
  LogoutRequestMessageResponseJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'message': instance.message,
};
