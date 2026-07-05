// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'message_view.g.dart';

@JsonSerializable()
class MessageView {
  const MessageView({
    this.authorMembershipId,
    this.authorName,
    this.authorColor,
    this.id = '',
    this.text = '',
    this.sentAt = '',
  });
  
  factory MessageView.fromJson(Map<String, Object?> json) => _$MessageViewFromJson(json);
  
  final String id;
  final String? authorMembershipId;
  final String? authorName;
  final String? authorColor;
  final String text;
  final String sentAt;

  Map<String, Object?> toJson() => _$MessageViewToJson(this);
}
