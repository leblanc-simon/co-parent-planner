// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'event_create_event_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

EventCreateEventRequest _$EventCreateEventRequestFromJson(
  Map<String, dynamic> json,
) => EventCreateEventRequest(
  title: json['title'] as String?,
  category: json['category'] as String?,
  responsibleMembershipId: json['responsibleMembershipId'] as String?,
  startsAt: json['startsAt'] as String?,
  wholeFamily: json['wholeFamily'] as bool? ?? false,
  allDay: json['allDay'] as bool? ?? false,
  childIds: (json['childIds'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  location: json['location'] as String?,
  description: json['description'] as String?,
  endsAt: json['endsAt'] as String?,
  recurrence: (json['recurrence'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as String?),
  ),
);

Map<String, dynamic> _$EventCreateEventRequestToJson(
  EventCreateEventRequest instance,
) => <String, dynamic>{
  'title': instance.title,
  'category': instance.category,
  'responsibleMembershipId': instance.responsibleMembershipId,
  'childIds': instance.childIds,
  'wholeFamily': instance.wholeFamily,
  'location': instance.location,
  'description': instance.description,
  'startsAt': instance.startsAt,
  'endsAt': instance.endsAt,
  'allDay': instance.allDay,
  'recurrence': instance.recurrence,
};
