// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'invitation_onboarding_token_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

InvitationOnboardingTokenResponse _$InvitationOnboardingTokenResponseFromJson(
  Map<String, dynamic> json,
) => InvitationOnboardingTokenResponse(
  accessToken: json['accessToken'] as String?,
  refreshToken: json['refreshToken'] as String?,
  expiresIn: (json['expiresIn'] as num?)?.toInt(),
  tokenType: json['tokenType'] as String?,
);

Map<String, dynamic> _$InvitationOnboardingTokenResponseToJson(
  InvitationOnboardingTokenResponse instance,
) => <String, dynamic>{
  'accessToken': instance.accessToken,
  'refreshToken': instance.refreshToken,
  'expiresIn': instance.expiresIn,
  'tokenType': instance.tokenType,
};
