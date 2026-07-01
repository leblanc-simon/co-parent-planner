// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'accept_invitation_request_family_resource_jsonld.g.dart';

/// US-ONB-1 sc.3-5 — accepter une invitation (squelette Lot 0). Authentification requise :.
/// l'e-mail du compte connecté doit correspondre à l'invité. Renvoie la famille rejointe.
@JsonSerializable()
class AcceptInvitationRequestFamilyResourceJsonld {
  const AcceptInvitationRequestFamilyResourceJsonld({
    required this.object6,
    required this.object7,
    this.id = '',
    this.name = '',
    this.type = '',
    this.timezone = '',
    this.expensesEnabled = false,
    this.hasCustody = false,
    this.role = '',
    this.isAdmin = false,
    this.object5,
    this.schoolZone,
    this.academy,
    this.handoverWeekday,
    this.handoverTime,
  });
  
  factory AcceptInvitationRequestFamilyResourceJsonld.fromJson(Map<String, Object?> json) => _$AcceptInvitationRequestFamilyResourceJsonldFromJson(json);
  
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
  final String name;
  final String type;
  final String timezone;
  final String? schoolZone;
  final String? academy;
  final bool expensesEnabled;
  final bool hasCustody;
  final int? handoverWeekday;
  final String? handoverTime;
  final String role;
  final bool isAdmin;

  Map<String, Object?> toJson() => _$AcceptInvitationRequestFamilyResourceJsonldToJson(this);
}
