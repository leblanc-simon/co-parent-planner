// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'register_and_accept_invitation_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RegisterAndAcceptInvitationRequest _$RegisterAndAcceptInvitationRequestFromJson(
  Map<String, dynamic> json,
) => RegisterAndAcceptInvitationRequest(
  fullName: json['fullName'] as String?,
  phone: json['phone'] as String?,
  locale: json['locale'] as String?,
  token: json['token'] as String? ?? '',
  password: json['password'] as String? ?? '',
);

Map<String, dynamic> _$RegisterAndAcceptInvitationRequestToJson(
  RegisterAndAcceptInvitationRequest instance,
) => <String, dynamic>{
  'token': instance.token,
  'password': instance.password,
  'fullName': instance.fullName,
  'phone': instance.phone,
  'locale': instance.locale,
};
