// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'family.g.dart';

/// Représentation d'une famille du point de vue du membre courant (US-ONB-1, US-HOME-1).
/// `role`/`isAdmin` reflètent la perspective de l'utilisateur connecté dans cette famille.
@JsonSerializable()
class Family {
  const Family({
    this.schoolZone,
    this.academy,
    this.id = '',
    this.name = '',
    this.type = '',
    this.timezone = '',
    this.expensesEnabled = false,
    this.hasCustody = false,
    this.role = '',
    this.isAdmin = false,
  });
  
  factory Family.fromJson(Map<String, Object?> json) => _$FamilyFromJson(json);
  
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

  Map<String, Object?> toJson() => _$FamilyToJson(this);
}
