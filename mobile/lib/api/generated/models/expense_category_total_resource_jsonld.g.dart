// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'expense_category_total_resource_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExpenseCategoryTotalResourceJsonld _$ExpenseCategoryTotalResourceJsonldFromJson(
  Map<String, dynamic> json,
) => ExpenseCategoryTotalResourceJsonld(
  category: json['category'] as String? ?? '',
  amountCents: (json['amountCents'] as num?)?.toInt() ?? 0,
  count: (json['count'] as num?)?.toInt() ?? 0,
  percent: (json['percent'] as num?)?.toInt() ?? 0,
);

Map<String, dynamic> _$ExpenseCategoryTotalResourceJsonldToJson(
  ExpenseCategoryTotalResourceJsonld instance,
) => <String, dynamic>{
  'category': instance.category,
  'amountCents': instance.amountCents,
  'count': instance.count,
  'percent': instance.percent,
};
