// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'expense_balance_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExpenseBalanceJsonld _$ExpenseBalanceJsonldFromJson(
  Map<String, dynamic> json,
) => ExpenseBalanceJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
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
  object5: json['@context'],
  net: (json['net'] as List<dynamic>?)
      ?.map((e) => ExpenseNetResourceJsonld.fromJson(e as Map<String, dynamic>))
      .toList(),
  debtorMembershipId: json['debtorMembershipId'] as String?,
  creditorMembershipId: json['creditorMembershipId'] as String?,
  byCategory: (json['byCategory'] as List<dynamic>?)
      ?.map(
        (e) => ExpenseCategoryTotalResourceJsonld.fromJson(
          e as Map<String, dynamic>,
        ),
      )
      .toList(),
);

Map<String, dynamic> _$ExpenseBalanceJsonldToJson(
  ExpenseBalanceJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
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
