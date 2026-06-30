// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'family_link_jsonld.g.dart';

/// Lien entre familles, vu depuis la famille active (US-LINK-1/2/3). N'expose **que** l'identité et.
/// le statut de l'autre famille — **aucune** donnée interne (membres, enfants, agenda). Pas d'overlay.
@JsonSerializable()
class FamilyLinkJsonld {
  const FamilyLinkJsonld({
    required this.object6,
    required this.object7,
    this.id = '',
    this.status = '',
    this.otherFamilyId = '',
    this.otherFamilyName = '',
    this.initiatedByMe = false,
    this.object5,
  });
  
  factory FamilyLinkJsonld.fromJson(Map<String, Object?> json) => _$FamilyLinkJsonldFromJson(json);
  
  /// Incorrect name has been replaced. Original name: `@context`.
  /// Incorrect name has been replaced. Original name: `@context`.
  @JsonKey(name: '@context')
  final dynamic object5;

  /// Incorrect name has been replaced. Original name: `@id`.
  @JsonKey(name: '@id')
  final String object6;

  /// Incorrect name has been replaced. Original name: `@type`.
  @JsonKey(name: '@type')
  final String object7;
  final String id;
  final String status;
  final String otherFamilyId;
  final String otherFamilyName;

  /// Vrai si la famille active est à l'origine du lien (sinon elle l'a reçu).
  final bool initiatedByMe;

  Map<String, Object?> toJson() => _$FamilyLinkJsonldToJson(this);
}
