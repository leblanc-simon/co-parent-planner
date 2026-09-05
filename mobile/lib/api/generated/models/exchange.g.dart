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
  myVotedOptionId: json['myVotedOptionId'] as String?,
  awaitingVoters: (json['awaitingVoters'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
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
  myVoteMaySettle: json['myVoteMaySettle'] as bool? ?? false,
  version: (json['version'] as num?)?.toInt() ?? 1,
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
  'myVotedOptionId': instance.myVotedOptionId,
  'awaitingVoters': instance.awaitingVoters,
  'myVoteMaySettle': instance.myVoteMaySettle,
  'lastMessageAt': instance.lastMessageAt,
  'version': instance.version,
  'options': instance.options,
  'messages': instance.messages,
};
