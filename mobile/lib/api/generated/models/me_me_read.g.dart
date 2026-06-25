// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'me_me_read.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

MeMeRead _$MeMeReadFromJson(Map<String, dynamic> json) => MeMeRead(
  email: json['email'] as String? ?? '',
  emailVerified: json['emailVerified'] as bool? ?? false,
  status: json['status'] as String? ?? '',
  id: json['id'] as String?,
  locale: json['locale'] as String?,
  fullName: json['fullName'] as String?,
  phone: json['phone'] as String?,
  avatarUrl: json['avatarUrl'] as String?,
);

Map<String, dynamic> _$MeMeReadToJson(MeMeRead instance) => <String, dynamic>{
  'id': instance.id,
  'email': instance.email,
  'locale': instance.locale,
  'fullName': instance.fullName,
  'phone': instance.phone,
  'avatarUrl': instance.avatarUrl,
  'emailVerified': instance.emailVerified,
  'status': instance.status,
};
