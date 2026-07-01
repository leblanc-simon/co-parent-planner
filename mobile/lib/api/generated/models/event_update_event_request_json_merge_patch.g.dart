// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'event_update_event_request_json_merge_patch.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

EventUpdateEventRequestJsonMergePatch
_$EventUpdateEventRequestJsonMergePatchFromJson(Map<String, dynamic> json) =>
    EventUpdateEventRequestJsonMergePatch(
      title: json['title'] as String?,
      category: json['category'] as String?,
      responsibleMembershipId: json['responsibleMembershipId'] as String?,
      childIds: (json['childIds'] as List<dynamic>?)
          ?.map((e) => e as String)
          .toList(),
      wholeFamily: json['wholeFamily'] as bool?,
      location: json['location'] as String?,
      description: json['description'] as String?,
      startsAt: json['startsAt'] as String?,
      endsAt: json['endsAt'] as String?,
      allDay: json['allDay'] as bool?,
      recurrence: (json['recurrence'] as Map<String, dynamic>?)?.map(
        (k, e) => MapEntry(k, e as String?),
      ),
      recurrenceDate: json['recurrenceDate'] as String?,
      expectedVersion: (json['expectedVersion'] as num?)?.toInt(),
    );

Map<String, dynamic> _$EventUpdateEventRequestJsonMergePatchToJson(
  EventUpdateEventRequestJsonMergePatch instance,
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
  'recurrenceDate': instance.recurrenceDate,
  'expectedVersion': instance.expectedVersion,
};
