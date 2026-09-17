// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'share_input.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ShareInput _$ShareInputFromJson(Map<String, dynamic> json) => ShareInput(
  membershipId: json['membershipId'] as String?,
  shareType: json['shareType'] as String?,
  value: (json['value'] as num?)?.toInt(),
);

Map<String, dynamic> _$ShareInputToJson(ShareInput instance) =>
    <String, dynamic>{
      'membershipId': instance.membershipId,
      'shareType': instance.shareType,
      'value': instance.value,
    };
