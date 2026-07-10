// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'admin_step_up.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

AdminStepUp _$AdminStepUpFromJson(Map<String, dynamic> json) => AdminStepUp(
  token: json['token'] as String? ?? '',
  expiresIn: (json['expiresIn'] as num?)?.toInt() ?? 0,
);

Map<String, dynamic> _$AdminStepUpToJson(AdminStepUp instance) =>
    <String, dynamic>{'token': instance.token, 'expiresIn': instance.expiresIn};
