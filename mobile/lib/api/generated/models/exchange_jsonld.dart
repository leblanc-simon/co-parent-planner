// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'message_view_jsonld.dart';
import 'option_view_jsonld.dart';

part 'exchange_jsonld.g.dart';

/// Échange exposé (Lot 6 §6.1). La collection `/families/{id}/exchanges?status=` renvoie les résumés.
/// (sans le fil) triés (§3.6) ; `GET /exchanges/{id}` renvoie le détail (options + décomptes + fil en.
/// clair). `POST .../decide` valide une option et renvoie l'échange (statut + options à jour) ;.
/// `POST .../read` marque le fil lu pour l'appelant (X5). Le.
/// marqueur `overdue` est dérivé (fuseau famille, §3.4).
@JsonSerializable()
class ExchangeJsonld {
  const ExchangeJsonld({
    required this.object6,
    required this.object7,
    this.id = '',
    this.type = '',
    this.title = '',
    this.status = '',
    this.overdue = false,
    this.createdAt = '',
    this.messageCount = 0,
    this.myVoteMaySettle = false,
    this.hasUnread = false,
    this.version = 1,
    this.object5,
    this.body,
    this.childId,
    this.childName,
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
  });
  
  factory ExchangeJsonld.fromJson(Map<String, Object?> json) => _$ExchangeJsonldFromJson(json);
  
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
  final String id;
  final String type;
  final String title;
  final String? body;
  final String? childId;

  /// Prénom de l'enfant concerné (X4). La carte de liste écrivait « Un enfant » — elle avait.
  /// l'identifiant mais pas le nom, et c'est le nom qui permet de trier d'un coup d'œil.
  final String? childName;
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

  /// L'échange a bougé depuis ma dernière lecture (X5).
  final bool hasUnread;

  /// Version de l'échange à renvoyer dans `expectedVersion` d'un PATCH (verrou optimiste, §4.2) —.
  /// même défaut que l'agenda : la contrainte existait côté serveur sans être lisible (C20).
  final int version;
  final List<OptionViewJsonld>? options;

  /// Fil complet (détail) ; vide en liste.
  final List<MessageViewJsonld>? messages;

  Map<String, Object?> toJson() => _$ExchangeJsonldToJson(this);
}
