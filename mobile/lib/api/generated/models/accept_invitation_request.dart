// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'accept_invitation_request.g.dart';

/// US-ONB-1 sc.3-5 — accepter une invitation (squelette Lot 0). Authentification requise :.
/// l'e-mail du compte connecté doit correspondre à l'invité. Renvoie la famille rejointe.
@JsonSerializable()
class AcceptInvitationRequest {
  const AcceptInvitationRequest({
    this.token = '',
  });
  
  factory AcceptInvitationRequest.fromJson(Map<String, Object?> json) => _$AcceptInvitationRequestFromJson(json);
  
  final String token;

  Map<String, Object?> toJson() => _$AcceptInvitationRequestToJson(this);
}
