// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'custody_day.g.dart';

/// Jour de garde effectif exposé (US-CUST-5).
@JsonSerializable()
class CustodyDay {
  const CustodyDay({
    this.parentMembershipId,
    this.date = '',
    this.source = '',
  });
  
  factory CustodyDay.fromJson(Map<String, Object?> json) => _$CustodyDayFromJson(json);
  
  final String date;
  final String? parentMembershipId;
  final String source;

  Map<String, Object?> toJson() => _$CustodyDayToJson(this);
}
