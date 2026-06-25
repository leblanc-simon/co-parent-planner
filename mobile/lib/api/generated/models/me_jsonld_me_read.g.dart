// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'me_jsonld_me_read.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

MeJsonldMeRead _$MeJsonldMeReadFromJson(Map<String, dynamic> json) =>
    MeJsonldMeRead(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      email: json['email'] as String? ?? '',
      emailVerified: json['emailVerified'] as bool? ?? false,
      status: json['status'] as String? ?? '',
      object5: json['@context'],
      id: json['id'] as String?,
      locale: json['locale'] as String?,
      fullName: json['fullName'] as String?,
      phone: json['phone'] as String?,
      avatarUrl: json['avatarUrl'] as String?,
    );

Map<String, dynamic> _$MeJsonldMeReadToJson(MeJsonldMeRead instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'id': instance.id,
      'email': instance.email,
      'locale': instance.locale,
      'fullName': instance.fullName,
      'phone': instance.phone,
      'avatarUrl': instance.avatarUrl,
      'emailVerified': instance.emailVerified,
      'status': instance.status,
    };
