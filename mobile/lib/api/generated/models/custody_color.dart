// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'custody_color.g.dart';

/// Couleur du planning choisie par le **membre courant** pour un parent donné (cdc §8.2).
///
/// `GET` liste ses préférences ; `PUT` fait un upsert idempotent `{targetMembershipId, palette}`.
/// Même forme que `OverlayPreference` (Lot 5) : c'est la même nature de donnée — une préférence.
/// d'affichage locale au spectateur, qui n'engage personne d'autre.
@JsonSerializable()
class CustodyColor {
  const CustodyColor({
    this.targetMembershipId = '',
    this.palette = '',
    this.border = '',
    this.soft = '',
    this.ink = '',
  });
  
  factory CustodyColor.fromJson(Map<String, Object?> json) => _$CustodyColorFromJson(json);
  
  final String targetMembershipId;
  final String palette;

  /// Triplet effectif, pour que le client n'ait pas à recopier la table du §8.2.
  final String border;
  final String soft;
  final String ink;

  Map<String, Object?> toJson() => _$CustodyColorToJson(this);
}
