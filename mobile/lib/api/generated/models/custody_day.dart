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
    this.segments,
    this.date = '',
    this.source = '',
  });
  
  factory CustodyDay.fromJson(Map<String, Object?> json) => _$CustodyDayFromJson(json);
  
  final String date;

  /// Parent **majoritaire** en heures : celui à qui la journée est comptée.
  final String? parentMembershipId;
  final String source;

  /// Découpage horaire de la journée (cdc §3.4), toujours renseigné et couvrant `00:00` → `24:00` :.
  /// une tranche pour une journée unie, deux quand la transmission tombe dedans, trois quand un.
  /// échange partiel est encadré par le rythme. `parent` est le membership, `source` l'origine.
  /// (`base`/`holiday`/`exception`) de la tranche.
  final List<Map<String, String?>>? segments;

  Map<String, Object?> toJson() => _$CustodyDayToJson(this);
}
