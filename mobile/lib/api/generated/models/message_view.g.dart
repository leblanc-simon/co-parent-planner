// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'message_view.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

MessageView _$MessageViewFromJson(Map<String, dynamic> json) => MessageView(
  authorMembershipId: json['authorMembershipId'] as String?,
  authorName: json['authorName'] as String?,
  authorColor: json['authorColor'] as String?,
  id: json['id'] as String? ?? '',
  text: json['text'] as String? ?? '',
  sentAt: json['sentAt'] as String? ?? '',
);

Map<String, dynamic> _$MessageViewToJson(MessageView instance) =>
    <String, dynamic>{
      'id': instance.id,
      'authorMembershipId': instance.authorMembershipId,
      'authorName': instance.authorName,
      'authorColor': instance.authorColor,
      'text': instance.text,
      'sentAt': instance.sentAt,
    };
