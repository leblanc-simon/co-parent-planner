// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'expense_net_resource_jsonld.g.dart';

@JsonSerializable()
class ExpenseNetResourceJsonld {
  const ExpenseNetResourceJsonld({
    this.membershipId = '',
    this.memberName = '',
    this.netCents = 0,
  });
  
  factory ExpenseNetResourceJsonld.fromJson(Map<String, Object?> json) => _$ExpenseNetResourceJsonldFromJson(json);
  
  final String membershipId;
  final String memberName;
  final int netCents;

  Map<String, Object?> toJson() => _$ExpenseNetResourceJsonldToJson(this);
}
