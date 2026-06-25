// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'realtime_token_jsonld.g.dart';

/// Jeton Mercure scopé par famille active (cdc §4.5, §3.12). Distinct du JWT d'accès API ; émis.
/// par le serveur (seule autorité sur `subscribe`). Au Lot 0, `subscribe` est **vide** (aucun topic.
/// métier) ; recalculé à chaque bascule de famille (nouveau X-Family-Id).
@JsonSerializable()
class RealtimeTokenJsonld {
  const RealtimeTokenJsonld({
    required this.object6,
    required this.object7,
    this.object5,
    this.token,
    this.hub,
  });
  
  factory RealtimeTokenJsonld.fromJson(Map<String, Object?> json) => _$RealtimeTokenJsonldFromJson(json);
  
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
  final String? token;
  final String? hub;

  Map<String, Object?> toJson() => _$RealtimeTokenJsonldToJson(this);
}
