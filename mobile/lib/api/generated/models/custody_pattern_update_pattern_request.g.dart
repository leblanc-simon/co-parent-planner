// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'custody_pattern_update_pattern_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CustodyPatternUpdatePatternRequest _$CustodyPatternUpdatePatternRequestFromJson(
  Map<String, dynamic> json,
) => CustodyPatternUpdatePatternRequest(
  kind: json['kind'] as String?,
  anchorDate: json['anchorDate'] as String?,
  handoverWeekday: (json['handoverWeekday'] as num?)?.toInt(),
  handoverTime: json['handoverTime'] as String?,
  dayStartTime: json['dayStartTime'] as String?,
  customPattern: (json['customPattern'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  settings: json['settings'],
  parentAMembershipId: json['parentAMembershipId'] as String?,
  parentBMembershipId: json['parentBMembershipId'] as String?,
);

Map<String, dynamic> _$CustodyPatternUpdatePatternRequestToJson(
  CustodyPatternUpdatePatternRequest instance,
) => <String, dynamic>{
  'kind': instance.kind,
  'anchorDate': instance.anchorDate,
  'handoverWeekday': instance.handoverWeekday,
  'handoverTime': instance.handoverTime,
  'dayStartTime': instance.dayStartTime,
  'customPattern': instance.customPattern,
  'settings': instance.settings,
  'parentAMembershipId': instance.parentAMembershipId,
  'parentBMembershipId': instance.parentBMembershipId,
};
