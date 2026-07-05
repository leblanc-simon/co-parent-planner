// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'exchange_create_exchange_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExchangeCreateExchangeRequest _$ExchangeCreateExchangeRequestFromJson(
  Map<String, dynamic> json,
) => ExchangeCreateExchangeRequest(
  type: json['type'] as String?,
  title: json['title'] as String?,
  body: json['body'] as String?,
  childId: json['childId'] as String?,
  deadline: json['deadline'] as String?,
  options: (json['options'] as List<dynamic>?)
      ?.map((e) => e as Map<String, dynamic>)
      .toList(),
);

Map<String, dynamic> _$ExchangeCreateExchangeRequestToJson(
  ExchangeCreateExchangeRequest instance,
) => <String, dynamic>{
  'type': instance.type,
  'title': instance.title,
  'body': instance.body,
  'childId': instance.childId,
  'deadline': instance.deadline,
  'options': instance.options,
};
