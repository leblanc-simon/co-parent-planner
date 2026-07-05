// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'message_create_message_request.g.dart';

/// Message du fil (Lot 6 §6.1). `POST /exchanges/{exchangeId}/messages` crée un message (texte.
/// chiffré au repos, renvoyé **en clair** à l'appelant autorisé). Sert aussi de vue imbriquée dans le.
/// détail d'un échange. `authorName`/`authorColor` sont `null` pour un auteur anonymisé (§4.8).
@JsonSerializable()
class MessageCreateMessageRequest {
  const MessageCreateMessageRequest({
    this.text,
  });
  
  factory MessageCreateMessageRequest.fromJson(Map<String, Object?> json) => _$MessageCreateMessageRequestFromJson(json);
  
  final String? text;

  Map<String, Object?> toJson() => _$MessageCreateMessageRequestToJson(this);
}
