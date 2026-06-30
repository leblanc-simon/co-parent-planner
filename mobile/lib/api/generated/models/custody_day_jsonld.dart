// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'custody_day_jsonld.g.dart';

/// Jour de garde effectif exposé (US-CUST-5).
@JsonSerializable()
class CustodyDayJsonld {
  const CustodyDayJsonld({
    required this.object6,
    required this.object7,
    this.date = '',
    this.source = '',
    this.object5,
    this.parentMembershipId,
  });
  
  factory CustodyDayJsonld.fromJson(Map<String, Object?> json) => _$CustodyDayJsonldFromJson(json);
  
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
  final String date;
  final String? parentMembershipId;
  final String source;

  Map<String, Object?> toJson() => _$CustodyDayJsonldToJson(this);
}
