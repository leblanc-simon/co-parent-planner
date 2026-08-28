// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'custody_pattern_update_pattern_request.g.dart';

/// Rythme de garde exposé (US-CUST-1).
@JsonSerializable()
class CustodyPatternUpdatePatternRequest {
  const CustodyPatternUpdatePatternRequest({
    this.kind,
    this.anchorDate,
    this.handoverWeekday,
    this.handoverTime,
    this.customPattern,
    this.settings,
    this.parentAMembershipId,
    this.parentBMembershipId,
  });
  
  factory CustodyPatternUpdatePatternRequest.fromJson(Map<String, Object?> json) => _$CustodyPatternUpdatePatternRequestFromJson(json);
  
  final String? kind;
  final String? anchorDate;
  final int? handoverWeekday;
  final String? handoverTime;
  final List<String>? customPattern;
  final dynamic settings;
  final String? parentAMembershipId;
  final String? parentBMembershipId;

  Map<String, Object?> toJson() => _$CustodyPatternUpdatePatternRequestToJson(this);
}
