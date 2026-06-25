// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'family_create_family_request_type.dart';

part 'family_create_family_request.g.dart';

/// Représentation d'une famille du point de vue du membre courant (US-ONB-1, US-HOME-1).
/// `role`/`isAdmin` reflètent la perspective de l'utilisateur connecté dans cette famille.
@JsonSerializable()
class FamilyCreateFamilyRequest {
  const FamilyCreateFamilyRequest({
    required this.name,
    required this.type,
  });
  
  factory FamilyCreateFamilyRequest.fromJson(Map<String, Object?> json) => _$FamilyCreateFamilyRequestFromJson(json);
  
  final String name;
  final FamilyCreateFamilyRequestType type;

  Map<String, Object?> toJson() => _$FamilyCreateFamilyRequestToJson(this);
}
