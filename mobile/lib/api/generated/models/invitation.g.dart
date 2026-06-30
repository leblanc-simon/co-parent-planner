// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'invitation.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Invitation _$InvitationFromJson(Map<String, dynamic> json) => Invitation(
  proposedRights: (json['proposedRights'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as String),
  ),
  expiresAt: json['expiresAt'] as String?,
  createdAt: json['createdAt'] as String?,
  id: json['id'] as String? ?? '',
  email: json['email'] as String? ?? '',
  role: json['role'] as String? ?? '',
  proposedAdmin: json['proposedAdmin'] as bool? ?? false,
  status: json['status'] as String? ?? '',
);

Map<String, dynamic> _$InvitationToJson(Invitation instance) =>
    <String, dynamic>{
      'id': instance.id,
      'email': instance.email,
      'role': instance.role,
      'proposedRights': instance.proposedRights,
      'proposedAdmin': instance.proposedAdmin,
      'status': instance.status,
      'expiresAt': instance.expiresAt,
      'createdAt': instance.createdAt,
    };
