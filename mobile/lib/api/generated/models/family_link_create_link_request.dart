// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'family_link_create_link_request.g.dart';

/// Lien entre familles, vu depuis la famille active (US-LINK-1/2/3). N'expose **que** l'identité et.
/// le statut de l'autre famille — **aucune** donnée interne (membres, enfants, agenda). Pas d'overlay.
@JsonSerializable()
class FamilyLinkCreateLinkRequest {
  const FamilyLinkCreateLinkRequest({
    required this.linkedFamilyId,
  });
  
  factory FamilyLinkCreateLinkRequest.fromJson(Map<String, Object?> json) => _$FamilyLinkCreateLinkRequestFromJson(json);
  
  final String? linkedFamilyId;

  Map<String, Object?> toJson() => _$FamilyLinkCreateLinkRequestToJson(this);
}
