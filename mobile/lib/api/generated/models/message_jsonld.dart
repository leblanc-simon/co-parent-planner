// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'message_jsonld.g.dart';

/// Message du fil (Lot 6 §6.1). `POST /exchanges/{exchangeId}/messages` crée un message (texte.
/// chiffré au repos, renvoyé **en clair** à l'appelant autorisé). Sert aussi de vue imbriquée dans le.
/// détail d'un échange. `authorName`/`authorColor` sont `null` pour un auteur anonymisé (§4.8).
@JsonSerializable()
class MessageJsonld {
  const MessageJsonld({
    required this.object6,
    required this.object7,
    this.id = '',
    this.exchangeId = '',
    this.text = '',
    this.sentAt = '',
    this.object5,
    this.authorMembershipId,
    this.authorName,
    this.authorColor,
  });
  
  factory MessageJsonld.fromJson(Map<String, Object?> json) => _$MessageJsonldFromJson(json);
  
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
  final String exchangeId;
  final String? authorMembershipId;
  final String? authorName;
  final String? authorColor;
  final String text;
  final String sentAt;

  Map<String, Object?> toJson() => _$MessageJsonldToJson(this);
}
