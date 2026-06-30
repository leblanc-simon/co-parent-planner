// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'custody_exception_jsonld.g.dart';

/// Exception de garde exposée (US-CUST-4).
@JsonSerializable()
class CustodyExceptionJsonld {
  const CustodyExceptionJsonld({
    required this.object6,
    required this.object7,
    this.id = '',
    this.startDate = '',
    this.endDate = '',
    this.object5,
    this.parentMembershipId,
    this.reason,
  });
  
  factory CustodyExceptionJsonld.fromJson(Map<String, Object?> json) => _$CustodyExceptionJsonldFromJson(json);
  
  /// Incorrect name has been replaced. Original name: `@context`.
  /// Incorrect name has been replaced. Original name: `@context`.
  @JsonKey(name: '@context')
  final dynamic object5;

  /// Incorrect name has been replaced. Original name: `@id`.
  @JsonKey(name: '@id')
  final String object6;

  /// Incorrect name has been replaced. Original name: `@type`.
  @JsonKey(name: '@type')
  final String object7;
  final String id;
  final String startDate;
  final String endDate;
  final String? parentMembershipId;
  final String? reason;

  Map<String, Object?> toJson() => _$CustodyExceptionJsonldToJson(this);
}
