// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'overlay_preference_update_overlay_preference_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

OverlayPreferenceUpdateOverlayPreferenceRequest
_$OverlayPreferenceUpdateOverlayPreferenceRequestFromJson(
  Map<String, dynamic> json,
) => OverlayPreferenceUpdateOverlayPreferenceRequest(
  familyLinkId: json['familyLinkId'] as String?,
  view: json['view'] as String?,
  enabled: json['enabled'] as bool? ?? false,
);

Map<String, dynamic> _$OverlayPreferenceUpdateOverlayPreferenceRequestToJson(
  OverlayPreferenceUpdateOverlayPreferenceRequest instance,
) => <String, dynamic>{
  'familyLinkId': instance.familyLinkId,
  'view': instance.view,
  'enabled': instance.enabled,
};
