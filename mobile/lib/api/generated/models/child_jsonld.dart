// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'child_jsonld.g.dart';

/// Profil enfant exposé (US-CH-1/4/5). `age` est **calculé** (jamais stocké) au fuseau famille.
@JsonSerializable()
class ChildJsonld {
  const ChildJsonld({
    required this.object6,
    required this.object7,
    this.id = '',
    this.firstName = '',
    this.color = '',
    this.initial = '',
    this.version = 1,
    this.object5,
    this.birthDate,
    this.age,
    this.schoolLabel,
    this.info,
  });
  
  factory ChildJsonld.fromJson(Map<String, Object?> json) => _$ChildJsonldFromJson(json);
  
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
  final String firstName;
  final String color;
  final String initial;
  final String? birthDate;
  final int? age;
  final String? schoolLabel;
  final List<Map<String, String>>? info;

  /// Version (verrou optimiste) : à renvoyer en `expectedVersion` pour une édition concurrente sûre.
  final int version;

  Map<String, Object?> toJson() => _$ChildJsonldToJson(this);
}
