// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'custody_pattern.g.dart';

/// Rythme de garde exposé (US-CUST-1).
@JsonSerializable()
class CustodyPattern {
  const CustodyPattern({
    this.anchorDate,
    this.customPattern,
    this.settings,
    this.parentAMembershipId,
    this.parentBMembershipId,
    this.id = '',
    this.kind = '',
    this.handoverWeekday = 0,
    this.handoverTime = '',
  });
  
  factory CustodyPattern.fromJson(Map<String, Object?> json) => _$CustodyPatternFromJson(json);
  
  final String id;
  final String kind;
  final String? anchorDate;
  final int handoverWeekday;
  final String handoverTime;
  final List<String>? customPattern;
  final Map<String, String?>? settings;
  final String? parentAMembershipId;
  final String? parentBMembershipId;

  Map<String, Object?> toJson() => _$CustodyPatternToJson(this);
}
