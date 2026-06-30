// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'family_update_family_request_json_merge_patch_school_zone.dart';
import 'family_update_family_request_json_merge_patch_type.dart';

part 'family_update_family_request_json_merge_patch.g.dart';

/// Représentation d'une famille du point de vue du membre courant (US-ONB-1, US-HOME-1).
/// `role`/`isAdmin` reflètent la perspective de l'utilisateur connecté dans cette famille.
@JsonSerializable()
class FamilyUpdateFamilyRequestJsonMergePatch {
  const FamilyUpdateFamilyRequestJsonMergePatch({
    this.name,
    this.type,
    this.timezone,
    this.schoolZone,
    this.academy,
    this.expensesEnabled,
    this.handoverWeekday,
    this.handoverTime,
  });
  
  factory FamilyUpdateFamilyRequestJsonMergePatch.fromJson(Map<String, Object?> json) => _$FamilyUpdateFamilyRequestJsonMergePatchFromJson(json);
  
  final String? name;
  final FamilyUpdateFamilyRequestJsonMergePatchType? type;
  final String? timezone;
  final FamilyUpdateFamilyRequestJsonMergePatchSchoolZone? schoolZone;
  final String? academy;
  final bool? expensesEnabled;

  /// Jour d'échange de garde : 1 (lundi) … 7 (dimanche) — ISO-8601.
  final int? handoverWeekday;

  /// Heure d'échange locale famille, format HH:MM (00:00–23:59).
  final String? handoverTime;

  Map<String, Object?> toJson() => _$FamilyUpdateFamilyRequestJsonMergePatchToJson(this);
}
