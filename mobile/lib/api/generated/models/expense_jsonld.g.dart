// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'expense_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExpenseJsonld _$ExpenseJsonldFromJson(Map<String, dynamic> json) =>
    ExpenseJsonld(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      id: json['id'] as String? ?? '',
      title: json['title'] as String? ?? '',
      category: json['category'] as String? ?? '',
      payerName: json['payerName'] as String? ?? '',
      amountCents: (json['amountCents'] as num?)?.toInt() ?? 0,
      currency: json['currency'] as String? ?? 'EUR',
      expenseDate: json['expenseDate'] as String? ?? '',
      settled: json['settled'] as bool? ?? false,
      object5: json['@context'],
      payerMembershipId: json['payerMembershipId'] as String?,
      childIds: (json['childIds'] as List<dynamic>?)
          ?.map((e) => e as String)
          .toList(),
      shares: (json['shares'] as List<dynamic>?)
          ?.map(
            (e) =>
                ExpenseShareResourceJsonld.fromJson(e as Map<String, dynamic>),
          )
          .toList(),
      reminderSentAt: json['reminderSentAt'] as String?,
      reminderAvailableAt: json['reminderAvailableAt'] as String?,
    );

Map<String, dynamic> _$ExpenseJsonldToJson(ExpenseJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'id': instance.id,
      'title': instance.title,
      'category': instance.category,
      'payerMembershipId': instance.payerMembershipId,
      'payerName': instance.payerName,
      'amountCents': instance.amountCents,
      'currency': instance.currency,
      'expenseDate': instance.expenseDate,
      'settled': instance.settled,
      'childIds': instance.childIds,
      'shares': instance.shares,
      'reminderSentAt': instance.reminderSentAt,
      'reminderAvailableAt': instance.reminderAvailableAt,
    };
