// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rgpd_purge_jsonld.g.dart';

/// Résultat d'une purge RGPD (US-RGPD-3). `POST /admin/rgpd/purge?dryRun=true|false` (admin technique.
/// + step-up) — **dry-run par défaut** : renvoie ce qui SERAIT purgé sans rien modifier.
@JsonSerializable()
class RgpdPurgeJsonld {
  const RgpdPurgeJsonld({
    required this.object6,
    required this.object7,
    this.mode = '',
    this.object5,
    this.totals,
    this.rules,
  });
  
  factory RgpdPurgeJsonld.fromJson(Map<String, Object?> json) => _$RgpdPurgeJsonldFromJson(json);
  
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
  final String mode;
  final Map<String, int>? totals;
  final List<Map<String, dynamic>>? rules;

  Map<String, Object?> toJson() => _$RgpdPurgeJsonldToJson(this);
}
