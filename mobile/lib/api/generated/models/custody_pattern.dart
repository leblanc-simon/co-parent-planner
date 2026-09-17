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
    this.dayStartTime = '',
  });
  
  factory CustodyPattern.fromJson(Map<String, Object?> json) => _$CustodyPatternFromJson(json);
  
  final String id;
  final String kind;
  final String? anchorDate;
  final int handoverWeekday;
  final String handoverTime;

  /// Heure à laquelle « la journée commence » : défaut proposé aux échanges partiels.
  final String dayStartTime;
  final List<String>? customPattern;

  /// Réglages du modèle `midweek_weekends` (US-CUST-1) : jours de week-end, parent de chaque jour.
  /// de semaine, parent qui ouvre l'alternance des week-ends.
  final dynamic settings;
  final String? parentAMembershipId;
  final String? parentBMembershipId;

  Map<String, Object?> toJson() => _$CustodyPatternToJson(this);
}
