// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'member_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

MemberJsonld _$MemberJsonldFromJson(Map<String, dynamic> json) => MemberJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  id: json['id'] as String? ?? '',
  userId: json['userId'] as String? ?? '',
  role: json['role'] as String? ?? '',
  isAdmin: json['isAdmin'] as bool? ?? false,
  self: json['self'] as bool? ?? false,
  object5: json['@context'],
  displayColor: json['displayColor'] as String?,
  fullName: json['fullName'] as String?,
  email: json['email'] as String?,
  phone: json['phone'] as String?,
  childId: json['childId'] as String?,
  rights: (json['rights'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as String),
  ),
);

Map<String, dynamic> _$MemberJsonldToJson(MemberJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'id': instance.id,
      'userId': instance.userId,
      'role': instance.role,
      'isAdmin': instance.isAdmin,
      'displayColor': instance.displayColor,
      'fullName': instance.fullName,
      'email': instance.email,
      'phone': instance.phone,
      'childId': instance.childId,
      'rights': instance.rights,
      'self': instance.self,
    };
