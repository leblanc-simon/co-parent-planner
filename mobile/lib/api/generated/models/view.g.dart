// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'view.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

View _$ViewFromJson(Map<String, dynamic> json) => View(
  object0: json['@id'] as String?,
  object1: json['@type'] as String?,
  first: json['first'] as String?,
  last: json['last'] as String?,
  previous: json['previous'] as String?,
  next: json['next'] as String?,
);

Map<String, dynamic> _$ViewToJson(View instance) => <String, dynamic>{
  '@id': instance.object0,
  '@type': instance.object1,
  'first': instance.first,
  'last': instance.last,
  'previous': instance.previous,
  'next': instance.next,
};
