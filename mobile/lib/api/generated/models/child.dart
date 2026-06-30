// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'child.g.dart';

/// Profil enfant exposé (US-CH-1/4/5). `age` est **calculé** (jamais stocké) au fuseau famille.
@JsonSerializable()
class Child {
  const Child({
    this.birthDate,
    this.age,
    this.schoolLabel,
    this.info,
    this.id = '',
    this.firstName = '',
    this.color = '',
    this.initial = '',
  });
  
  factory Child.fromJson(Map<String, Object?> json) => _$ChildFromJson(json);
  
  final String id;
  final String firstName;
  final String color;
  final String initial;
  final String? birthDate;
  final int? age;
  final String? schoolLabel;
  final List<Map<String, String>>? info;

  Map<String, Object?> toJson() => _$ChildToJson(this);
}
