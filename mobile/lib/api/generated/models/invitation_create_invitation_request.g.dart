// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'invitation_create_invitation_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

InvitationCreateInvitationRequest _$InvitationCreateInvitationRequestFromJson(
  Map<String, dynamic> json,
) => InvitationCreateInvitationRequest(
  email: json['email'] as String?,
  role: json['role'] == null
      ? null
      : InvitationCreateInvitationRequestRole.fromJson(json['role'] as String?),
  rights: (json['rights'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as String),
  ),
  isAdmin: json['isAdmin'] as bool?,
);

Map<String, dynamic> _$InvitationCreateInvitationRequestToJson(
  InvitationCreateInvitationRequest instance,
) => <String, dynamic>{
  'email': instance.email,
  'role': instance.role,
  'rights': instance.rights,
  'isAdmin': instance.isAdmin,
};
