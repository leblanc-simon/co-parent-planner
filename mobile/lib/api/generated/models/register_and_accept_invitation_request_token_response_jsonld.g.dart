// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'register_and_accept_invitation_request_token_response_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RegisterAndAcceptInvitationRequestTokenResponseJsonld
_$RegisterAndAcceptInvitationRequestTokenResponseJsonldFromJson(
  Map<String, dynamic> json,
) => RegisterAndAcceptInvitationRequestTokenResponseJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  object5: json['@context'],
  accessToken: json['accessToken'] as String?,
  refreshToken: json['refreshToken'] as String?,
  expiresIn: (json['expiresIn'] as num?)?.toInt(),
  tokenType: json['tokenType'] as String?,
);

Map<String, dynamic>
_$RegisterAndAcceptInvitationRequestTokenResponseJsonldToJson(
  RegisterAndAcceptInvitationRequestTokenResponseJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'accessToken': instance.accessToken,
  'refreshToken': instance.refreshToken,
  'expiresIn': instance.expiresIn,
  'tokenType': instance.tokenType,
};
