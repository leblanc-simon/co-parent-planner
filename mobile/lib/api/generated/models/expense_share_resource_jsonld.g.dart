// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'expense_share_resource_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExpenseShareResourceJsonld _$ExpenseShareResourceJsonldFromJson(
  Map<String, dynamic> json,
) => ExpenseShareResourceJsonld(
  membershipId: json['membershipId'] as String? ?? '',
  memberName: json['memberName'] as String? ?? '',
  shareType: json['shareType'] as String? ?? '',
  value: (json['value'] as num?)?.toInt() ?? 0,
  effectiveCents: (json['effectiveCents'] as num?)?.toInt() ?? 0,
);

Map<String, dynamic> _$ExpenseShareResourceJsonldToJson(
  ExpenseShareResourceJsonld instance,
) => <String, dynamic>{
  'membershipId': instance.membershipId,
  'memberName': instance.memberName,
  'shareType': instance.shareType,
  'value': instance.value,
  'effectiveCents': instance.effectiveCents,
};
