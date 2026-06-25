// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/accept_invitation_request.dart';
import '../models/accept_invitation_request_family_resource.dart';

part 'accept_invitation_request_client.g.dart';

@RestApi()
abstract class AcceptInvitationRequestClient {
  factory AcceptInvitationRequestClient(Dio dio, {String? baseUrl}) = _AcceptInvitationRequestClient;

  /// Creates a AcceptInvitationRequest resource.
  ///
  /// Creates a AcceptInvitationRequest resource.
  @POST('/api/invitations/accept')
  Future<AcceptInvitationRequestFamilyResource> apiInvitationsacceptPost({
    @Body() required AcceptInvitationRequest body,
  });
}
