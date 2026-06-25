// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'realtime_token.g.dart';

/// Jeton Mercure scopé par famille active (cdc §4.5, §3.12). Distinct du JWT d'accès API ; émis.
/// par le serveur (seule autorité sur `subscribe`). Au Lot 0, `subscribe` est **vide** (aucun topic.
/// métier) ; recalculé à chaque bascule de famille (nouveau X-Family-Id).
@JsonSerializable()
class RealtimeToken {
  const RealtimeToken({
    this.token,
    this.hub,
  });
  
  factory RealtimeToken.fromJson(Map<String, Object?> json) => _$RealtimeTokenFromJson(json);
  
  final String? token;
  final String? hub;

  Map<String, Object?> toJson() => _$RealtimeTokenToJson(this);
}
