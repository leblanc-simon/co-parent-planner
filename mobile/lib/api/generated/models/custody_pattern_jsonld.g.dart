// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'custody_pattern_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CustodyPatternJsonld _$CustodyPatternJsonldFromJson(
  Map<String, dynamic> json,
) => CustodyPatternJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  id: json['id'] as String? ?? '',
  kind: json['kind'] as String? ?? '',
  handoverWeekday: (json['handoverWeekday'] as num?)?.toInt() ?? 0,
  handoverTime: json['handoverTime'] as String? ?? '',
  object5: json['@context'],
  anchorDate: json['anchorDate'] as String?,
  customPattern: (json['customPattern'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  settings: (json['settings'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as String?),
  ),
  parentAMembershipId: json['parentAMembershipId'] as String?,
  parentBMembershipId: json['parentBMembershipId'] as String?,
);

Map<String, dynamic> _$CustodyPatternJsonldToJson(
  CustodyPatternJsonld instance,
) => <String, dynamic>{
  '@context': instance.object5,
  '@id': instance.object6,
  '@type': instance.object7,
  'id': instance.id,
  'kind': instance.kind,
  'anchorDate': instance.anchorDate,
  'handoverWeekday': instance.handoverWeekday,
  'handoverTime': instance.handoverTime,
  'customPattern': instance.customPattern,
  'settings': instance.settings,
  'parentAMembershipId': instance.parentAMembershipId,
  'parentBMembershipId': instance.parentBMembershipId,
};
