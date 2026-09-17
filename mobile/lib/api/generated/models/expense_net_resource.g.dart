// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'expense_net_resource.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExpenseNetResource _$ExpenseNetResourceFromJson(Map<String, dynamic> json) =>
    ExpenseNetResource(
      membershipId: json['membershipId'] as String? ?? '',
      memberName: json['memberName'] as String? ?? '',
      netCents: (json['netCents'] as num?)?.toInt() ?? 0,
    );

Map<String, dynamic> _$ExpenseNetResourceToJson(ExpenseNetResource instance) =>
    <String, dynamic>{
      'membershipId': instance.membershipId,
      'memberName': instance.memberName,
      'netCents': instance.netCents,
    };
