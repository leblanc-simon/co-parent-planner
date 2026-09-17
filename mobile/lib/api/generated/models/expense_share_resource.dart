// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'expense_share_resource.g.dart';

@JsonSerializable()
class ExpenseShareResource {
  const ExpenseShareResource({
    this.membershipId = '',
    this.memberName = '',
    this.shareType = '',
    this.value = 0,
    this.effectiveCents = 0,
  });
  
  factory ExpenseShareResource.fromJson(Map<String, Object?> json) => _$ExpenseShareResourceFromJson(json);
  
  final String membershipId;
  final String memberName;
  final String shareType;
  final int value;
  final int effectiveCents;

  Map<String, Object?> toJson() => _$ExpenseShareResourceToJson(this);
}
