// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'exchange_create_exchange_request.g.dart';

/// Échange exposé (Lot 6 §6.1). La collection `/families/{id}/exchanges?status=` renvoie les résumés.
/// (sans le fil) triés (§3.6) ; `GET /exchanges/{id}` renvoie le détail (options + décomptes + fil en.
/// clair). `POST .../decide` valide une option et renvoie l'échange (statut + options à jour). Le.
/// marqueur `overdue` est dérivé (fuseau famille, §3.4).
@JsonSerializable()
class ExchangeCreateExchangeRequest {
  const ExchangeCreateExchangeRequest({
    this.type,
    this.title,
    this.body,
    this.childId,
    this.deadline,
    this.options,
  });
  
  factory ExchangeCreateExchangeRequest.fromJson(Map<String, Object?> json) => _$ExchangeCreateExchangeRequestFromJson(json);
  
  final String? type;
  final String? title;
  final String? body;
  final String? childId;
  final String? deadline;
  final List<Map<String, dynamic>>? options;

  Map<String, Object?> toJson() => _$ExchangeCreateExchangeRequestToJson(this);
}
