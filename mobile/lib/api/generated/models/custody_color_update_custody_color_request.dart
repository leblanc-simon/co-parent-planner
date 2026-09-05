// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'custody_color_update_custody_color_request_palette.dart';

part 'custody_color_update_custody_color_request.g.dart';

/// Couleur du planning choisie par le **membre courant** pour un parent donné (cdc §8.2).
///
/// `GET` liste ses préférences ; `PUT` fait un upsert idempotent `{targetMembershipId, palette}`.
/// Même forme que `OverlayPreference` (Lot 5) : c'est la même nature de donnée — une préférence.
/// d'affichage locale au spectateur, qui n'engage personne d'autre.
@JsonSerializable()
class CustodyColorUpdateCustodyColorRequest {
  const CustodyColorUpdateCustodyColorRequest({
    required this.targetMembershipId,
    required this.palette,
  });
  
  factory CustodyColorUpdateCustodyColorRequest.fromJson(Map<String, Object?> json) => _$CustodyColorUpdateCustodyColorRequestFromJson(json);
  
  final String? targetMembershipId;

  /// Palette fermée : une valeur hors catalogue est refusée, jamais silencieusement ignorée.
  final CustodyColorUpdateCustodyColorRequestPalette? palette;

  Map<String, Object?> toJson() => _$CustodyColorUpdateCustodyColorRequestToJson(this);
}
