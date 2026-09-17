// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'expense_balance.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExpenseBalance _$ExpenseBalanceFromJson(Map<String, dynamic> json) =>
    ExpenseBalance(
      net: (json['net'] as List<dynamic>?)
          ?.map((e) => ExpenseNetResource.fromJson(e as Map<String, dynamic>))
          .toList(),
      debtorMembershipId: json['debtorMembershipId'] as String?,
      creditorMembershipId: json['creditorMembershipId'] as String?,
      byCategory: (json['byCategory'] as List<dynamic>?)
          ?.map(
            (e) => ExpenseCategoryTotalResource.fromJson(
              e as Map<String, dynamic>,
            ),
          )
          .toList(),
      familyId: json['familyId'] as String? ?? '',
      debtorName: json['debtorName'] as String? ?? '',
      creditorName: json['creditorName'] as String? ?? '',
      amountCents: (json['amountCents'] as num?)?.toInt() ?? 0,
      balanced: json['balanced'] as bool? ?? true,
      totalCents: (json['totalCents'] as num?)?.toInt() ?? 0,
      settledCents: (json['settledCents'] as num?)?.toInt() ?? 0,
      unsettledCents: (json['unsettledCents'] as num?)?.toInt() ?? 0,
      expenseCount: (json['expenseCount'] as num?)?.toInt() ?? 0,
      excludedFromBalanceCount:
          (json['excludedFromBalanceCount'] as num?)?.toInt() ?? 0,
    );

Map<String, dynamic> _$ExpenseBalanceToJson(ExpenseBalance instance) =>
    <String, dynamic>{
      'familyId': instance.familyId,
      'net': instance.net,
      'debtorMembershipId': instance.debtorMembershipId,
      'debtorName': instance.debtorName,
      'creditorMembershipId': instance.creditorMembershipId,
      'creditorName': instance.creditorName,
      'amountCents': instance.amountCents,
      'balanced': instance.balanced,
      'totalCents': instance.totalCents,
      'settledCents': instance.settledCents,
      'unsettledCents': instance.unsettledCents,
      'expenseCount': instance.expenseCount,
      'byCategory': instance.byCategory,
      'excludedFromBalanceCount': instance.excludedFromBalanceCount,
    };
