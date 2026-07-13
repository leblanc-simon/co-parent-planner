// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'register_and_accept_invitation_request_token_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RegisterAndAcceptInvitationRequestTokenResponse
_$RegisterAndAcceptInvitationRequestTokenResponseFromJson(
  Map<String, dynamic> json,
) => RegisterAndAcceptInvitationRequestTokenResponse(
  accessToken: json['accessToken'] as String?,
  refreshToken: json['refreshToken'] as String?,
  expiresIn: (json['expiresIn'] as num?)?.toInt(),
  tokenType: json['tokenType'] as String?,
);

Map<String, dynamic> _$RegisterAndAcceptInvitationRequestTokenResponseToJson(
  RegisterAndAcceptInvitationRequestTokenResponse instance,
) => <String, dynamic>{
  'accessToken': instance.accessToken,
  'refreshToken': instance.refreshToken,
  'expiresIn': instance.expiresIn,
  'tokenType': instance.tokenType,
};
