// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'custody_stats_jsonld.g.dart';

/// Statistiques de garde sur une fenêtre (US-CUST-5).
@JsonSerializable()
class CustodyStatsJsonld {
  const CustodyStatsJsonld({
    required this.object6,
    required this.object7,
    this.id = 'stats',
    this.parentACount = 0,
    this.parentBCount = 0,
    this.otherCount = 0,
    this.total = 0,
    this.pctA = 0,
    this.pctB = 0,
    this.object5,
    this.from,
    this.to,
  });
  
  factory CustodyStatsJsonld.fromJson(Map<String, Object?> json) => _$CustodyStatsJsonldFromJson(json);
  
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
  final int parentACount;
  final int parentBCount;
  final int otherCount;
  final int total;
  final num pctA;
  final num pctB;
  final String? from;
  final String? to;

  Map<String, Object?> toJson() => _$CustodyStatsJsonldToJson(this);
}
