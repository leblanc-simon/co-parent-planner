// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'message.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Message _$MessageFromJson(Map<String, dynamic> json) => Message(
  authorMembershipId: json['authorMembershipId'] as String?,
  authorName: json['authorName'] as String?,
  authorColor: json['authorColor'] as String?,
  id: json['id'] as String? ?? '',
  exchangeId: json['exchangeId'] as String? ?? '',
  text: json['text'] as String? ?? '',
  sentAt: json['sentAt'] as String? ?? '',
);

Map<String, dynamic> _$MessageToJson(Message instance) => <String, dynamic>{
  'id': instance.id,
  'exchangeId': instance.exchangeId,
  'authorMembershipId': instance.authorMembershipId,
  'authorName': instance.authorName,
  'authorColor': instance.authorColor,
  'text': instance.text,
  'sentAt': instance.sentAt,
};
