// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'custody_exception.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CustodyException _$CustodyExceptionFromJson(Map<String, dynamic> json) =>
    CustodyException(
      parentMembershipId: json['parentMembershipId'] as String?,
      reason: json['reason'] as String?,
      id: json['id'] as String? ?? '',
      startDate: json['startDate'] as String? ?? '',
      endDate: json['endDate'] as String? ?? '',
    );

Map<String, dynamic> _$CustodyExceptionToJson(CustodyException instance) =>
    <String, dynamic>{
      'id': instance.id,
      'startDate': instance.startDate,
      'endDate': instance.endDate,
      'parentMembershipId': instance.parentMembershipId,
      'reason': instance.reason,
    };
