// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'login_request_token_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

LoginRequestTokenResponse _$LoginRequestTokenResponseFromJson(
  Map<String, dynamic> json,
) => LoginRequestTokenResponse(
  accessToken: json['accessToken'] as String?,
  refreshToken: json['refreshToken'] as String?,
  expiresIn: (json['expiresIn'] as num?)?.toInt(),
  tokenType: json['tokenType'] as String?,
);

Map<String, dynamic> _$LoginRequestTokenResponseToJson(
  LoginRequestTokenResponse instance,
) => <String, dynamic>{
  'accessToken': instance.accessToken,
  'refreshToken': instance.refreshToken,
  'expiresIn': instance.expiresIn,
  'tokenType': instance.tokenType,
};
