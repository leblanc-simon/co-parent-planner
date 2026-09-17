// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'expense_net_resource_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExpenseNetResourceJsonld _$ExpenseNetResourceJsonldFromJson(
  Map<String, dynamic> json,
) => ExpenseNetResourceJsonld(
  membershipId: json['membershipId'] as String? ?? '',
  memberName: json['memberName'] as String? ?? '',
  netCents: (json['netCents'] as num?)?.toInt() ?? 0,
);

Map<String, dynamic> _$ExpenseNetResourceJsonldToJson(
  ExpenseNetResourceJsonld instance,
) => <String, dynamic>{
  'membershipId': instance.membershipId,
  'memberName': instance.memberName,
  'netCents': instance.netCents,
};
