// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'child_update_child_request_json_merge_patch.g.dart';

/// Profil enfant exposé (US-CH-1/4/5). `age` est **calculé** (jamais stocké) au fuseau famille.
@JsonSerializable()
class ChildUpdateChildRequestJsonMergePatch {
  const ChildUpdateChildRequestJsonMergePatch({
    this.firstName,
    this.color,
    this.birthDate,
    this.schoolLabel,
    this.info,
    this.expectedVersion,
  });
  
  factory ChildUpdateChildRequestJsonMergePatch.fromJson(Map<String, Object?> json) => _$ChildUpdateChildRequestJsonMergePatchFromJson(json);
  
  final String? firstName;
  final String? color;
  final String? birthDate;
  final String? schoolLabel;
  final List<Map<String, String?>>? info;

  /// Version attendue (verrou optimiste, §4.2) : si fournie et différente de la version courante,.
  /// l'édition est rejetée par un 409 (évite l'écrasement concurrent de `info`). Optionnel.
  final int? expectedVersion;

  Map<String, Object?> toJson() => _$ChildUpdateChildRequestJsonMergePatchToJson(this);
}
