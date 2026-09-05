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
    this.myVotedOptionId,
    this.awaitingVoters,
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
    this.myVoteMaySettle = false,
    this.version = 1,
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

  /// État du tour de vote d'une **décision**, du point de vue de l'appelant (X1).
  final String? myVotedOptionId;

  /// Noms des électeurs qui n'ont pas encore voté, **hors moi**.
  final List<String>? awaitingVoters;

  /// Je suis le dernier électeur à ne pas avoir voté : mon vote **peut** trancher — « peut », car.
  /// une égalité laisse l'échange ouvert (§3.3).
  final bool myVoteMaySettle;
  final String? lastMessageAt;

  /// Version de l'échange à renvoyer dans `expectedVersion` d'un PATCH (verrou optimiste, §4.2) —.
  /// même défaut que l'agenda : la contrainte existait côté serveur sans être lisible (C20).
  final int version;
  final List<OptionView>? options;
  final List<MessageView>? messages;

  Map<String, Object?> toJson() => _$ExchangeToJson(this);
}
