// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'view.g.dart';

@JsonSerializable()
class View {
  const View({
    this.object0,
    this.object1,
    this.first,
    this.last,
    this.previous,
    this.next,
  });
  
  factory View.fromJson(Map<String, Object?> json) => _$ViewFromJson(json);
  
  /// Incorrect name has been replaced. Original name: `@id`.
  @JsonKey(name: '@id')
  final String? object0;

  /// Incorrect name has been replaced. Original name: `@type`.
  @JsonKey(name: '@type')
  final String? object1;
  final String? first;
  final String? last;
  final String? previous;
  final String? next;

  Map<String, Object?> toJson() => _$ViewToJson(this);
}
