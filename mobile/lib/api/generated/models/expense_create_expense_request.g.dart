// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'expense_create_expense_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExpenseCreateExpenseRequest _$ExpenseCreateExpenseRequestFromJson(
  Map<String, dynamic> json,
) => ExpenseCreateExpenseRequest(
  title: json['title'] as String?,
  category: json['category'] as String?,
  payerMembershipId: json['payerMembershipId'] as String?,
  amountCents: (json['amountCents'] as num?)?.toInt(),
  expenseDate: json['expenseDate'] as String?,
  childIds: (json['childIds'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  shares: (json['shares'] as List<dynamic>?)
      ?.map((e) => ShareInput.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$ExpenseCreateExpenseRequestToJson(
  ExpenseCreateExpenseRequest instance,
) => <String, dynamic>{
  'title': instance.title,
  'category': instance.category,
  'payerMembershipId': instance.payerMembershipId,
  'amountCents': instance.amountCents,
  'expenseDate': instance.expenseDate,
  'childIds': instance.childIds,
  'shares': instance.shares,
};
