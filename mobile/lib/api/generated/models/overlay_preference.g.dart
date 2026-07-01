// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'overlay_preference.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

OverlayPreference _$OverlayPreferenceFromJson(Map<String, dynamic> json) =>
    OverlayPreference(
      familyLinkId: json['familyLinkId'] as String? ?? '',
      view: json['view'] as String? ?? '',
      enabled: json['enabled'] as bool? ?? false,
    );

Map<String, dynamic> _$OverlayPreferenceToJson(OverlayPreference instance) =>
    <String, dynamic>{
      'familyLinkId': instance.familyLinkId,
      'view': instance.view,
      'enabled': instance.enabled,
    };
