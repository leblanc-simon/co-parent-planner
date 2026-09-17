// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'expense_category_total_resource.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExpenseCategoryTotalResource _$ExpenseCategoryTotalResourceFromJson(
  Map<String, dynamic> json,
) => ExpenseCategoryTotalResource(
  category: json['category'] as String? ?? '',
  amountCents: (json['amountCents'] as num?)?.toInt() ?? 0,
  count: (json['count'] as num?)?.toInt() ?? 0,
  percent: (json['percent'] as num?)?.toInt() ?? 0,
);

Map<String, dynamic> _$ExpenseCategoryTotalResourceToJson(
  ExpenseCategoryTotalResource instance,
) => <String, dynamic>{
  'category': instance.category,
  'amountCents': instance.amountCents,
  'count': instance.count,
  'percent': instance.percent,
};
