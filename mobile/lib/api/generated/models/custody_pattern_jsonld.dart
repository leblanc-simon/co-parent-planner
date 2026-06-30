// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'custody_pattern_jsonld.g.dart';

/// Rythme de garde exposé (US-CUST-1).
@JsonSerializable()
class CustodyPatternJsonld {
  const CustodyPatternJsonld({
    required this.object6,
    required this.object7,
    this.id = '',
    this.kind = '',
    this.handoverWeekday = 0,
    this.handoverTime = '',
    this.object5,
    this.anchorDate,
    this.customPattern,
    this.settings,
    this.parentAMembershipId,
    this.parentBMembershipId,
  });
  
  factory CustodyPatternJsonld.fromJson(Map<String, Object?> json) => _$CustodyPatternJsonldFromJson(json);
  
  /// Incorrect name has been replaced. Original name: `@context`.
  /// Incorrect name has been replaced. Original name: `@context`.
  @JsonKey(name: '@context')
  final dynamic object5;

  /// Incorrect name has been replaced. Original name: `@id`.
  @JsonKey(name: '@id')
  final String object6;

  /// Incorrect name has been replaced. Original name: `@type`.
  @JsonKey(name: '@type')
  final String object7;
  final String id;
  final String kind;
  final String? anchorDate;
  final int handoverWeekday;
  final String handoverTime;
  final List<String>? customPattern;
  final Map<String, String?>? settings;
  final String? parentAMembershipId;
  final String? parentBMembershipId;

  Map<String, Object?> toJson() => _$CustodyPatternJsonldToJson(this);
}
