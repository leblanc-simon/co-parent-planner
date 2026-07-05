// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'message_view.dart';
import 'option_view.dart';

part 'exchange.g.dart';

/// Échange exposé (Lot 6 §6.1). La collection `/families/{id}/exchanges?status=` renvoie les résumés.
/// (sans le fil) triés (§3.6) ; `GET /exchanges/{id}` renvoie le détail (options + décomptes + fil en.
/// clair). `POST .../decide` valide une option et renvoie l'échange (statut + options à jour). Le.
/// marqueur `overdue` est dérivé (fuseau famille, §3.4).
@JsonSerializable()
class Exchange {
  const Exchange({
    this.body,
    this.childId,
    this.authorMembershipId,
    this.authorName,
    this.authorColor,
    this.deadline,
    this.decidedOptionId,
    this.lastMessageAt,
    this.options,
    this.messages,
    this.id = '',
    this.type = '',
    this.title = '',
    this.status = '',
    this.overdue = false,
    this.createdAt = '',
    this.messageCount = 0,
  });
  
  factory Exchange.fromJson(Map<String, Object?> json) => _$ExchangeFromJson(json);
  
  final String id;
  final String type;
  final String title;
  final String? body;
  final String? childId;
  final String? authorMembershipId;
  final String? authorName;
  final String? authorColor;
  final String? deadline;
  final String status;
  final String? decidedOptionId;
  final bool overdue;
  final String createdAt;
  final int messageCount;
  final String? lastMessageAt;
  final List<OptionView>? options;
  final List<MessageView>? messages;

  Map<String, Object?> toJson() => _$ExchangeToJson(this);
}
