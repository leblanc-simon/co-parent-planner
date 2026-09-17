// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'expense_share_resource.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExpenseShareResource _$ExpenseShareResourceFromJson(
  Map<String, dynamic> json,
) => ExpenseShareResource(
  membershipId: json['membershipId'] as String? ?? '',
  memberName: json['memberName'] as String? ?? '',
  shareType: json['shareType'] as String? ?? '',
  value: (json['value'] as num?)?.toInt() ?? 0,
  effectiveCents: (json['effectiveCents'] as num?)?.toInt() ?? 0,
);

Map<String, dynamic> _$ExpenseShareResourceToJson(
  ExpenseShareResource instance,
) => <String, dynamic>{
  'membershipId': instance.membershipId,
  'memberName': instance.memberName,
  'shareType': instance.shareType,
  'value': instance.value,
  'effectiveCents': instance.effectiveCents,
};
