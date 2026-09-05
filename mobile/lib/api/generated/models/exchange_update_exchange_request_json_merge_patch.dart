// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'exchange_update_exchange_request_json_merge_patch.g.dart';

/// Échange exposé (Lot 6 §6.1). La collection `/families/{id}/exchanges?status=` renvoie les résumés.
/// (sans le fil) triés (§3.6) ; `GET /exchanges/{id}` renvoie le détail (options + décomptes + fil en.
/// clair). `POST .../decide` valide une option et renvoie l'échange (statut + options à jour) ;.
/// `POST .../read` marque le fil lu pour l'appelant (X5). Le.
/// marqueur `overdue` est dérivé (fuseau famille, §3.4).
@JsonSerializable()
class ExchangeUpdateExchangeRequestJsonMergePatch {
  const ExchangeUpdateExchangeRequestJsonMergePatch({
    this.status,
    this.decidedOptionId,
    this.deadline,
    this.expectedVersion,
  });
  
  factory ExchangeUpdateExchangeRequestJsonMergePatch.fromJson(Map<String, Object?> json) => _$ExchangeUpdateExchangeRequestJsonMergePatchFromJson(json);
  
  final String? status;
  final String? decidedOptionId;
  final String? deadline;
  final int? expectedVersion;

  Map<String, Object?> toJson() => _$ExchangeUpdateExchangeRequestJsonMergePatchToJson(this);
}
