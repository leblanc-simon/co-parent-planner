// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'custody_color_update_custody_color_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CustodyColorUpdateCustodyColorRequest
_$CustodyColorUpdateCustodyColorRequestFromJson(Map<String, dynamic> json) =>
    CustodyColorUpdateCustodyColorRequest(
      targetMembershipId: json['targetMembershipId'] as String?,
      palette: json['palette'] == null
          ? null
          : CustodyColorUpdateCustodyColorRequestPalette.fromJson(
              json['palette'] as String?,
            ),
    );

Map<String, dynamic> _$CustodyColorUpdateCustodyColorRequestToJson(
  CustodyColorUpdateCustodyColorRequest instance,
) => <String, dynamic>{
  'targetMembershipId': instance.targetMembershipId,
  'palette': instance.palette,
};
