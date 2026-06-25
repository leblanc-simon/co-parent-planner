// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refresh_request_token_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RefreshRequestTokenResponse _$RefreshRequestTokenResponseFromJson(
  Map<String, dynamic> json,
) => RefreshRequestTokenResponse(
  accessToken: json['accessToken'] as String?,
  refreshToken: json['refreshToken'] as String?,
  expiresIn: (json['expiresIn'] as num?)?.toInt(),
  tokenType: json['tokenType'] as String?,
);

Map<String, dynamic> _$RefreshRequestTokenResponseToJson(
  RefreshRequestTokenResponse instance,
) => <String, dynamic>{
  'accessToken': instance.accessToken,
  'refreshToken': instance.refreshToken,
  'expiresIn': instance.expiresIn,
  'tokenType': instance.tokenType,
};
