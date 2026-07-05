// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'message_view_jsonld.g.dart';

@JsonSerializable()
class MessageViewJsonld {
  const MessageViewJsonld({
    this.authorMembershipId,
    this.authorName,
    this.authorColor,
    this.id = '',
    this.text = '',
    this.sentAt = '',
  });
  
  factory MessageViewJsonld.fromJson(Map<String, Object?> json) => _$MessageViewJsonldFromJson(json);
  
  final String id;
  final String? authorMembershipId;
  final String? authorName;
  final String? authorColor;
  final String text;
  final String sentAt;

  Map<String, Object?> toJson() => _$MessageViewJsonldToJson(this);
}
