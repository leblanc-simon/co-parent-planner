// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'accept_invitation_request_family_resource.g.dart';

/// US-ONB-1 sc.3-5 — accepter une invitation (squelette Lot 0). Authentification requise :.
/// l'e-mail du compte connecté doit correspondre à l'invité. Renvoie la famille rejointe.
@JsonSerializable()
class AcceptInvitationRequestFamilyResource {
  const AcceptInvitationRequestFamilyResource({
    this.schoolZone,
    this.academy,
    this.handoverWeekday,
    this.handoverTime,
    this.id = '',
    this.name = '',
    this.type = '',
    this.timezone = '',
    this.expensesEnabled = false,
    this.hasCustody = false,
    this.role = '',
    this.isAdmin = false,
  });
  
  factory AcceptInvitationRequestFamilyResource.fromJson(Map<String, Object?> json) => _$AcceptInvitationRequestFamilyResourceFromJson(json);
  
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

  Map<String, Object?> toJson() => _$AcceptInvitationRequestFamilyResourceToJson(this);
}
