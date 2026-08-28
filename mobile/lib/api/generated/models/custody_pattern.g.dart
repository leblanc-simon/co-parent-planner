// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'custody_pattern.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CustodyPattern _$CustodyPatternFromJson(Map<String, dynamic> json) =>
    CustodyPattern(
      anchorDate: json['anchorDate'] as String?,
      customPattern: (json['customPattern'] as List<dynamic>?)
          ?.map((e) => e as String)
          .toList(),
      settings: json['settings'],
      parentAMembershipId: json['parentAMembershipId'] as String?,
      parentBMembershipId: json['parentBMembershipId'] as String?,
      id: json['id'] as String? ?? '',
      kind: json['kind'] as String? ?? '',
      handoverWeekday: (json['handoverWeekday'] as num?)?.toInt() ?? 0,
      handoverTime: json['handoverTime'] as String? ?? '',
    );

Map<String, dynamic> _$CustodyPatternToJson(CustodyPattern instance) =>
    <String, dynamic>{
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
