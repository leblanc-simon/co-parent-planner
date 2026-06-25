// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refresh_request_token_response_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RefreshRequestTokenResponseJsonld _$RefreshRequestTokenResponseJsonldFromJson(
  Map<String, dynamic> json,
) => RefreshRequestTokenResponseJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  object5: json['@context'],
  accessToken: json['accessToken'] as String?,
  refreshToken: json['refreshToken'] as String?,
  expiresIn: (json['expiresIn'] as num?)?.toInt(),
  tokenType: json['tokenType'] as String?,
);

Map<String, dynamic> _$RefreshRequestTokenResponseJsonldToJson(
  RefreshRequestTokenResponseJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'accessToken': instance.accessToken,
  'refreshToken': instance.refreshToken,
  'expiresIn': instance.expiresIn,
  'tokenType': instance.tokenType,
};
