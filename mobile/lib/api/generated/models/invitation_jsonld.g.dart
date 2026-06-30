// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'invitation_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

InvitationJsonld _$InvitationJsonldFromJson(Map<String, dynamic> json) =>
    InvitationJsonld(
      object6: json['@id'] as String,
      object7: json['@type'] as String,
      id: json['id'] as String? ?? '',
      email: json['email'] as String? ?? '',
      role: json['role'] as String? ?? '',
      proposedAdmin: json['proposedAdmin'] as bool? ?? false,
      status: json['status'] as String? ?? '',
      object5: json['@context'],
      proposedRights: (json['proposedRights'] as Map<String, dynamic>?)?.map(
        (k, e) => MapEntry(k, e as String),
      ),
      expiresAt: json['expiresAt'] as String?,
      createdAt: json['createdAt'] as String?,
    );

Map<String, dynamic> _$InvitationJsonldToJson(InvitationJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'id': instance.id,
      'email': instance.email,
      'role': instance.role,
      'proposedRights': instance.proposedRights,
      'proposedAdmin': instance.proposedAdmin,
      'status': instance.status,
      'expiresAt': instance.expiresAt,
      'createdAt': instance.createdAt,
    };
