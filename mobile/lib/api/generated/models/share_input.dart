// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'share_input.g.dart';

@JsonSerializable()
class ShareInput {
  const ShareInput({
    this.membershipId,
    this.shareType,
    this.value,
  });
  
  factory ShareInput.fromJson(Map<String, Object?> json) => _$ShareInputFromJson(json);
  
  final String? membershipId;

  /// `percent` ou `amount` — homogène sur toute la dépense (§3.1, règle 2).
  final String? shareType;
  final int? value;

  Map<String, Object?> toJson() => _$ShareInputToJson(this);
}
