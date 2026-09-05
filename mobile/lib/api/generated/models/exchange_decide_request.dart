// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'exchange_decide_request.g.dart';

/// Échange exposé (Lot 6 §6.1). La collection `/families/{id}/exchanges?status=` renvoie les résumés.
/// (sans le fil) triés (§3.6) ; `GET /exchanges/{id}` renvoie le détail (options + décomptes + fil en.
/// clair). `POST .../decide` valide une option et renvoie l'échange (statut + options à jour) ;.
/// `POST .../read` marque le fil lu pour l'appelant (X5). Le.
/// marqueur `overdue` est dérivé (fuseau famille, §3.4).
@JsonSerializable()
class ExchangeDecideRequest {
  const ExchangeDecideRequest({
    this.optionId,
  });
  
  factory ExchangeDecideRequest.fromJson(Map<String, Object?> json) => _$ExchangeDecideRequestFromJson(json);
  
  final String? optionId;

  Map<String, Object?> toJson() => _$ExchangeDecideRequestToJson(this);
}
