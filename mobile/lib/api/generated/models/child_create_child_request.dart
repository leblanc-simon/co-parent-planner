// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'child_create_child_request.g.dart';

/// Profil enfant exposé (US-CH-1/4/5). `age` est **calculé** (jamais stocké) au fuseau famille.
@JsonSerializable()
class ChildCreateChildRequest {
  const ChildCreateChildRequest({
    required this.firstName,
    this.color,
    this.birthDate,
    this.schoolLabel,
    this.info,
  });
  
  factory ChildCreateChildRequest.fromJson(Map<String, Object?> json) => _$ChildCreateChildRequestFromJson(json);
  
  final String? firstName;
  final String? color;

  /// Date ISO (YYYY-MM-DD) ou null.
  final String? birthDate;
  final String? schoolLabel;
  final List<Map<String, String?>>? info;

  Map<String, Object?> toJson() => _$ChildCreateChildRequestToJson(this);
}
