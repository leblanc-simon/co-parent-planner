// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'custody_stats.g.dart';

/// Statistiques de garde sur une fenêtre (US-CUST-5).
@JsonSerializable()
class CustodyStats {
  const CustodyStats({
    this.from,
    this.to,
    this.id = 'stats',
    this.parentACount = 0,
    this.parentBCount = 0,
    this.otherCount = 0,
    this.total = 0,
    this.daysInWindow = 0,
    this.pctA = 0,
    this.pctB = 0,
  });
  
  factory CustodyStats.fromJson(Map<String, Object?> json) => _$CustodyStatsFromJson(json);
  
  final String id;
  final int parentACount;
  final int parentBCount;
  final int otherCount;
  final int total;

  /// Jours calendaires de la fenêtre ; `total` < `daysInWindow` = fenêtre partiellement générée.
  final int daysInWindow;
  final num pctA;
  final num pctB;
  final String? from;
  final String? to;

  Map<String, Object?> toJson() => _$CustodyStatsToJson(this);
}
