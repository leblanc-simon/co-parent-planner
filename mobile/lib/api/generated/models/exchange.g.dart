// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'exchange.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Exchange _$ExchangeFromJson(Map<String, dynamic> json) => Exchange(
  body: json['body'] as String?,
  childId: json['childId'] as String?,
  authorMembershipId: json['authorMembershipId'] as String?,
  authorName: json['authorName'] as String?,
  authorColor: json['authorColor'] as String?,
  deadline: json['deadline'] as String?,
  decidedOptionId: json['decidedOptionId'] as String?,
  lastMessageAt: json['lastMessageAt'] as String?,
  options: (json['options'] as List<dynamic>?)
      ?.map((e) => OptionView.fromJson(e as Map<String, dynamic>))
      .toList(),
  messages: (json['messages'] as List<dynamic>?)
      ?.map((e) => MessageView.fromJson(e as Map<String, dynamic>))
      .toList(),
  id: json['id'] as String? ?? '',
  type: json['type'] as String? ?? '',
  title: json['title'] as String? ?? '',
  status: json['status'] as String? ?? '',
  overdue: json['overdue'] as bool? ?? false,
  createdAt: json['createdAt'] as String? ?? '',
  messageCount: (json['messageCount'] as num?)?.toInt() ?? 0,
);

Map<String, dynamic> _$ExchangeToJson(Exchange instance) => <String, dynamic>{
  'id': instance.id,
  'type': instance.type,
  'title': instance.title,
  'body': instance.body,
  'childId': instance.childId,
  'authorMembershipId': instance.authorMembershipId,
  'authorName': instance.authorName,
  'authorColor': instance.authorColor,
  'deadline': instance.deadline,
  'status': instance.status,
  'decidedOptionId': instance.decidedOptionId,
  'overdue': instance.overdue,
  'createdAt': instance.createdAt,
  'messageCount': instance.messageCount,
  'lastMessageAt': instance.lastMessageAt,
  'options': instance.options,
  'messages': instance.messages,
};
