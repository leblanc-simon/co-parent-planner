// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'option_view.g.dart';

@JsonSerializable()
class OptionView {
  const OptionView({
    this.sub,
    this.id = '',
    this.label = '',
    this.position = 0,
    this.voteCount = 0,
    this.decided = false,
  });
  
  factory OptionView.fromJson(Map<String, Object?> json) => _$OptionViewFromJson(json);
  
  final String id;
  final String label;
  final String? sub;
  final int position;
  final int voteCount;
  final bool decided;

  Map<String, Object?> toJson() => _$OptionViewToJson(this);
}
