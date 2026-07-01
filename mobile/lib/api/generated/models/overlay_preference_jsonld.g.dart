// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'overlay_preference_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

OverlayPreferenceJsonld _$OverlayPreferenceJsonldFromJson(
  Map<String, dynamic> json,
) => OverlayPreferenceJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  familyLinkId: json['familyLinkId'] as String? ?? '',
  view: json['view'] as String? ?? '',
  enabled: json['enabled'] as bool? ?? false,
  object5: json['@context'],
);

Map<String, dynamic> _$OverlayPreferenceJsonldToJson(
  OverlayPreferenceJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'familyLinkId': instance.familyLinkId,
  'view': instance.view,
  'enabled': instance.enabled,
};
