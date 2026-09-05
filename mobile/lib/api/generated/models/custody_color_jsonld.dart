// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'custody_color_jsonld.g.dart';

/// Couleur du planning choisie par le **membre courant** pour un parent donné (cdc §8.2).
///
/// `GET` liste ses préférences ; `PUT` fait un upsert idempotent `{targetMembershipId, palette}`.
/// Même forme que `OverlayPreference` (Lot 5) : c'est la même nature de donnée — une préférence.
/// d'affichage locale au spectateur, qui n'engage personne d'autre.
@JsonSerializable()
class CustodyColorJsonld {
  const CustodyColorJsonld({
    required this.object6,
    required this.object7,
    this.targetMembershipId = '',
    this.palette = '',
    this.border = '',
    this.soft = '',
    this.ink = '',
    this.object5,
  });
  
  factory CustodyColorJsonld.fromJson(Map<String, Object?> json) => _$CustodyColorJsonldFromJson(json);
  
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
  final String targetMembershipId;
  final String palette;

  /// Triplet effectif, pour que le client n'ait pas à recopier la table du §8.2.
  final String border;
  final String soft;
  final String ink;

  Map<String, Object?> toJson() => _$CustodyColorJsonldToJson(this);
}
