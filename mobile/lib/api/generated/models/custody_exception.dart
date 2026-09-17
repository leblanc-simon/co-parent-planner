// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'custody_exception.g.dart';

/// Exception de garde exposée (US-CUST-4).
@JsonSerializable()
class CustodyException {
  const CustodyException({
    this.startTime,
    this.endTime,
    this.parentMembershipId,
    this.reason,
    this.id = '',
    this.startDate = '',
    this.endDate = '',
  });
  
  factory CustodyException.fromJson(Map<String, Object?> json) => _$CustodyExceptionFromJson(json);
  
  final String id;
  final String startDate;
  final String endDate;

  /// Bornes horaires (HH:MM) ; `null` des deux côtés = journées entières.
  final String? startTime;
  final String? endTime;
  final String? parentMembershipId;
  final String? reason;

  Map<String, Object?> toJson() => _$CustodyExceptionToJson(this);
}
