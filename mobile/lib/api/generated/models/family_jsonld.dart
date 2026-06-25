// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'family_jsonld.g.dart';

/// Représentation d'une famille du point de vue du membre courant (US-ONB-1, US-HOME-1).
/// `role`/`isAdmin` reflètent la perspective de l'utilisateur connecté dans cette famille.
@JsonSerializable()
class FamilyJsonld {
  const FamilyJsonld({
    required this.object6,
    required this.object7,
    this.id = '',
    this.name = '',
    this.type = '',
    this.timezone = '',
    this.expensesEnabled = false,
    this.hasCustody = false,
    this.role = '',
    this.isAdmin = false,
    this.object5,
    this.schoolZone,
    this.academy,
  });
  
  factory FamilyJsonld.fromJson(Map<String, Object?> json) => _$FamilyJsonldFromJson(json);
  
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
  final String name;
  final String type;
  final String timezone;
  final String? schoolZone;
  final String? academy;
  final bool expensesEnabled;
  final bool hasCustody;
  final String role;
  final bool isAdmin;

  Map<String, Object?> toJson() => _$FamilyJsonldToJson(this);
}
