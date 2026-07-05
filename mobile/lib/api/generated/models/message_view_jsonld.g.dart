// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'message_view_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

MessageViewJsonld _$MessageViewJsonldFromJson(Map<String, dynamic> json) =>
    MessageViewJsonld(
      authorMembershipId: json['authorMembershipId'] as String?,
      authorName: json['authorName'] as String?,
      authorColor: json['authorColor'] as String?,
      id: json['id'] as String? ?? '',
      text: json['text'] as String? ?? '',
      sentAt: json['sentAt'] as String? ?? '',
    );

Map<String, dynamic> _$MessageViewJsonldToJson(MessageViewJsonld instance) =>
    <String, dynamic>{
      'id': instance.id,
      'authorMembershipId': instance.authorMembershipId,
      'authorName': instance.authorName,
      'authorColor': instance.authorColor,
      'text': instance.text,
      'sentAt': instance.sentAt,
    };
