// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'expense_category_total_resource_jsonld.g.dart';

@JsonSerializable()
class ExpenseCategoryTotalResourceJsonld {
  const ExpenseCategoryTotalResourceJsonld({
    this.category = '',
    this.amountCents = 0,
    this.count = 0,
    this.percent = 0,
  });
  
  factory ExpenseCategoryTotalResourceJsonld.fromJson(Map<String, Object?> json) => _$ExpenseCategoryTotalResourceJsonldFromJson(json);
  
  final String category;
  final int amountCents;
  final int count;

  /// Part dans le total engagé, en pourcentage entier (dérivé d'affichage, ±1 sur la somme).
  final int percent;

  Map<String, Object?> toJson() => _$ExpenseCategoryTotalResourceJsonldToJson(this);
}
