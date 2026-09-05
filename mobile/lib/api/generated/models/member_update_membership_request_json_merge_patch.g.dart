// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'member_update_membership_request_json_merge_patch.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

MemberUpdateMembershipRequestJsonMergePatch
_$MemberUpdateMembershipRequestJsonMergePatchFromJson(
  Map<String, dynamic> json,
) => MemberUpdateMembershipRequestJsonMergePatch(
  role: json['role'] == null
      ? null
      : MemberUpdateMembershipRequestJsonMergePatchRole.fromJson(
          json['role'] as String?,
        ),
  isAdmin: json['isAdmin'] as bool?,
  rights: (json['rights'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as String),
  ),
  displayColor: json['displayColor'] as String?,
  partnerMembershipId: json['partnerMembershipId'] as String?,
);

Map<String, dynamic> _$MemberUpdateMembershipRequestJsonMergePatchToJson(
  MemberUpdateMembershipRequestJsonMergePatch instance,
) => <String, dynamic>{
  'role': instance.role,
  'isAdmin': instance.isAdmin,
  'rights': instance.rights,
  'displayColor': instance.displayColor,
  'partnerMembershipId': instance.partnerMembershipId,
};
