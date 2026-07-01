// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'event_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

EventJsonld _$EventJsonldFromJson(Map<String, dynamic> json) => EventJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  id: json['id'] as String? ?? '',
  title: json['title'] as String? ?? '',
  category: json['category'] as String? ?? '',
  wholeFamily: json['wholeFamily'] as bool? ?? false,
  startsAt: json['startsAt'] as String? ?? '',
  allDay: json['allDay'] as bool? ?? false,
  isRecurring: json['isRecurring'] as bool? ?? false,
  isOverride: json['isOverride'] as bool? ?? false,
  object5: json['@context'],
  seriesId: json['seriesId'] as String?,
  responsibleMembershipId: json['responsibleMembershipId'] as String?,
  childIds: (json['childIds'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  location: json['location'] as String?,
  description: json['description'] as String?,
  endsAt: json['endsAt'] as String?,
  recurrenceDate: json['recurrenceDate'] as String?,
  recurrence: json['recurrence'] as Map<String, dynamic>?,
);

Map<String, dynamic> _$EventJsonldToJson(EventJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'id': instance.id,
      'seriesId': instance.seriesId,
      'title': instance.title,
      'category': instance.category,
      'responsibleMembershipId': instance.responsibleMembershipId,
      'wholeFamily': instance.wholeFamily,
      'childIds': instance.childIds,
      'location': instance.location,
      'description': instance.description,
      'startsAt': instance.startsAt,
      'endsAt': instance.endsAt,
      'allDay': instance.allDay,
      'recurrenceDate': instance.recurrenceDate,
      'isRecurring': instance.isRecurring,
      'isOverride': instance.isOverride,
      'recurrence': instance.recurrence,
    };
