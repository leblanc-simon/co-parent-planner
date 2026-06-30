// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'custody_exception_create_exception_request.g.dart';

/// Exception de garde exposée (US-CUST-4).
@JsonSerializable()
class CustodyExceptionCreateExceptionRequest {
  const CustodyExceptionCreateExceptionRequest({
    required this.startDate,
    required this.endDate,
    required this.parentMembershipId,
    this.reason,
  });
  
  factory CustodyExceptionCreateExceptionRequest.fromJson(Map<String, Object?> json) => _$CustodyExceptionCreateExceptionRequestFromJson(json);
  
  final String? startDate;
  final String? endDate;
  final String? parentMembershipId;
  final String? reason;

  Map<String, Object?> toJson() => _$CustodyExceptionCreateExceptionRequestToJson(this);
}
