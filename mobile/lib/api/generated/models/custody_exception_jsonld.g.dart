// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'custody_exception_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CustodyExceptionJsonld _$CustodyExceptionJsonldFromJson(
  Map<String, dynamic> json,
) => CustodyExceptionJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  id: json['id'] as String? ?? '',
  startDate: json['startDate'] as String? ?? '',
  endDate: json['endDate'] as String? ?? '',
  object5: json['@context'],
  parentMembershipId: json['parentMembershipId'] as String?,
  reason: json['reason'] as String?,
);

Map<String, dynamic> _$CustodyExceptionJsonldToJson(
  CustodyExceptionJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'id': instance.id,
  'startDate': instance.startDate,
  'endDate': instance.endDate,
  'parentMembershipId': instance.parentMembershipId,
  'reason': instance.reason,
};
