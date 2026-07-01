// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'overlay_preference_jsonld.g.dart';

/// Préférence d'overlay du **membre courant** (US-LINK-1, Lot 5 §6.2), par lien et par vue.
/// `GET` liste ses préférences ; `PUT` fait un upsert idempotent `{familyLinkId, view, enabled}`.
@JsonSerializable()
class OverlayPreferenceJsonld {
  const OverlayPreferenceJsonld({
    required this.object6,
    required this.object7,
    this.familyLinkId = '',
    this.view = '',
    this.enabled = false,
    this.object5,
  });
  
  factory OverlayPreferenceJsonld.fromJson(Map<String, Object?> json) => _$OverlayPreferenceJsonldFromJson(json);
  
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
  final String familyLinkId;
  final String view;
  final bool enabled;

  Map<String, Object?> toJson() => _$OverlayPreferenceJsonldToJson(this);
}
