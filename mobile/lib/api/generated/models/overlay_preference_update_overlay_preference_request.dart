// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'overlay_preference_update_overlay_preference_request.g.dart';

/// Préférence d'overlay du **membre courant** (US-LINK-1, Lot 5 §6.2), par lien et par vue.
/// `GET` liste ses préférences ; `PUT` fait un upsert idempotent `{familyLinkId, view, enabled}`.
@JsonSerializable()
class OverlayPreferenceUpdateOverlayPreferenceRequest {
  const OverlayPreferenceUpdateOverlayPreferenceRequest({
    required this.familyLinkId,
    required this.view,
    this.enabled = false,
  });
  
  factory OverlayPreferenceUpdateOverlayPreferenceRequest.fromJson(Map<String, Object?> json) => _$OverlayPreferenceUpdateOverlayPreferenceRequestFromJson(json);
  
  final String? familyLinkId;
  final String? view;
  final bool enabled;

  Map<String, Object?> toJson() => _$OverlayPreferenceUpdateOverlayPreferenceRequestToJson(this);
}
