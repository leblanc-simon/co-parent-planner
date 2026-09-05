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
    this.myVote = false,
  });
  
  factory OptionView.fromJson(Map<String, Object?> json) => _$OptionViewFromJson(json);
  
  final String id;
  final String label;
  final String? sub;
  final int position;
  final int voteCount;
  final bool decided;

  /// Cette option est **celle que j'ai validée** (X1). Le décompte seul ne le disait pas : après.
  /// avoir voté, il passait de 0 à 1 sans jamais indiquer que ce 1, c'était vous.
  final bool myVote;

  Map<String, Object?> toJson() => _$OptionViewToJson(this);
}
