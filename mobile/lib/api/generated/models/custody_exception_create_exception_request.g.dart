// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'custody_exception_create_exception_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CustodyExceptionCreateExceptionRequest
_$CustodyExceptionCreateExceptionRequestFromJson(Map<String, dynamic> json) =>
    CustodyExceptionCreateExceptionRequest(
      startDate: json['startDate'] as String?,
      endDate: json['endDate'] as String?,
      parentMembershipId: json['parentMembershipId'] as String?,
      reason: json['reason'] as String?,
    );

Map<String, dynamic> _$CustodyExceptionCreateExceptionRequestToJson(
  CustodyExceptionCreateExceptionRequest instance,
) => <String, dynamic>{
  'startDate': instance.startDate,
  'endDate': instance.endDate,
  'parentMembershipId': instance.parentMembershipId,
  'reason': instance.reason,
};
