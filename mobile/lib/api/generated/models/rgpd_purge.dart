// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rgpd_purge.g.dart';

/// Résultat d'une purge RGPD (US-RGPD-3). `POST /admin/rgpd/purge?dryRun=true|false` (admin technique.
/// + step-up) — **dry-run par défaut** : renvoie ce qui SERAIT purgé sans rien modifier.
@JsonSerializable()
class RgpdPurge {
  const RgpdPurge({
    this.totals,
    this.rules,
    this.mode = '',
  });
  
  factory RgpdPurge.fromJson(Map<String, Object?> json) => _$RgpdPurgeFromJson(json);
  
  final String mode;
  final Map<String, int>? totals;
  final List<Map<String, dynamic>>? rules;

  Map<String, Object?> toJson() => _$RgpdPurgeToJson(this);
}
