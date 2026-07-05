// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'exchange_update_exchange_request_json_merge_patch.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExchangeUpdateExchangeRequestJsonMergePatch
_$ExchangeUpdateExchangeRequestJsonMergePatchFromJson(
  Map<String, dynamic> json,
) => ExchangeUpdateExchangeRequestJsonMergePatch(
  status: json['status'] as String?,
  decidedOptionId: json['decidedOptionId'] as String?,
  deadline: json['deadline'] as String?,
  expectedVersion: (json['expectedVersion'] as num?)?.toInt(),
);

Map<String, dynamic> _$ExchangeUpdateExchangeRequestJsonMergePatchToJson(
  ExchangeUpdateExchangeRequestJsonMergePatch instance,
) => <String, dynamic>{
  'status': instance.status,
  'decidedOptionId': instance.decidedOptionId,
  'deadline': instance.deadline,
  'expectedVersion': instance.expectedVersion,
};
