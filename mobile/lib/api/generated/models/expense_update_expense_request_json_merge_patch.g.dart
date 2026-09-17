// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'expense_update_expense_request_json_merge_patch.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExpenseUpdateExpenseRequestJsonMergePatch
_$ExpenseUpdateExpenseRequestJsonMergePatchFromJson(
  Map<String, dynamic> json,
) => ExpenseUpdateExpenseRequestJsonMergePatch(
  title: json['title'] as String?,
  category: json['category'] as String?,
  payerMembershipId: json['payerMembershipId'] as String?,
  amountCents: (json['amountCents'] as num?)?.toInt(),
  expenseDate: json['expenseDate'] as String?,
  settled: json['settled'] as bool?,
  childIds: (json['childIds'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  shares: (json['shares'] as List<dynamic>?)
      ?.map((e) => ShareInput.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$ExpenseUpdateExpenseRequestJsonMergePatchToJson(
  ExpenseUpdateExpenseRequestJsonMergePatch instance,
) => <String, dynamic>{
  'title': instance.title,
  'category': instance.category,
  'payerMembershipId': instance.payerMembershipId,
  'amountCents': instance.amountCents,
  'expenseDate': instance.expenseDate,
  'settled': instance.settled,
  'childIds': instance.childIds,
  'shares': instance.shares,
};
