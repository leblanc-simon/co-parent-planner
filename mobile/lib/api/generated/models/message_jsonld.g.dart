// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'message_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

MessageJsonld _$MessageJsonldFromJson(Map<String, dynamic> json) =>
    MessageJsonld(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      id: json['id'] as String? ?? '',
      exchangeId: json['exchangeId'] as String? ?? '',
      text: json['text'] as String? ?? '',
      sentAt: json['sentAt'] as String? ?? '',
      object5: json['@context'],
      authorMembershipId: json['authorMembershipId'] as String?,
      authorName: json['authorName'] as String?,
      authorColor: json['authorColor'] as String?,
    );

Map<String, dynamic> _$MessageJsonldToJson(MessageJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'id': instance.id,
      'exchangeId': instance.exchangeId,
      'authorMembershipId': instance.authorMembershipId,
      'authorName': instance.authorName,
      'authorColor': instance.authorColor,
      'text': instance.text,
      'sentAt': instance.sentAt,
    };
