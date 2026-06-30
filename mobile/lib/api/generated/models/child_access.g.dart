// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'child_access.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ChildAccess _$ChildAccessFromJson(Map<String, dynamic> json) => ChildAccess(
  displayColor: json['displayColor'] as String?,
  membershipId: json['membershipId'] as String? ?? '',
  name: json['name'] as String? ?? '',
  role: json['role'] as String? ?? '',
  level: json['level'] as String? ?? '',
  isOwnProfile: json['isOwnProfile'] as bool? ?? false,
);

Map<String, dynamic> _$ChildAccessToJson(ChildAccess instance) =>
    <String, dynamic>{
      'membershipId': instance.membershipId,
      'name': instance.name,
      'role': instance.role,
      'level': instance.level,
      'isOwnProfile': instance.isOwnProfile,
      'displayColor': instance.displayColor,
    };
