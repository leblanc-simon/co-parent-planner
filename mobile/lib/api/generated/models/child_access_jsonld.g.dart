// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'child_access_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ChildAccessJsonld _$ChildAccessJsonldFromJson(Map<String, dynamic> json) =>
    ChildAccessJsonld(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      membershipId: json['membershipId'] as String? ?? '',
      name: json['name'] as String? ?? '',
      role: json['role'] as String? ?? '',
      level: json['level'] as String? ?? '',
      isOwnProfile: json['isOwnProfile'] as bool? ?? false,
      object5: json['@context'],
      displayColor: json['displayColor'] as String?,
    );

Map<String, dynamic> _$ChildAccessJsonldToJson(ChildAccessJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'membershipId': instance.membershipId,
      'name': instance.name,
      'role': instance.role,
      'level': instance.level,
      'isOwnProfile': instance.isOwnProfile,
      'displayColor': instance.displayColor,
    };
