// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/invitation.dart';
import '../models/invitation_create_invitation_request.dart';

part 'invitation_client.g.dart';

@RestApi()
abstract class InvitationClient {
  factory InvitationClient(Dio dio, {String? baseUrl}) = _InvitationClient;

  /// Retrieves the collection of Invitation resources.
  ///
  /// Retrieves the collection of Invitation resources.
  ///
  /// [familyId] - FamilyResource identifier.
  @GET('/api/families/{familyId}/invitations')
  Future<List<Invitation>> apiFamiliesFamilyIdinvitationsGetCollection({
    @Path('familyId') required String familyId,
  });

  /// Creates a Invitation resource.
  ///
  /// Creates a Invitation resource.
  ///
  /// [familyId] - FamilyResource identifier.
  @POST('/api/families/{familyId}/invitations')
  Future<Invitation> apiFamiliesFamilyIdinvitationsPost({
    @Path('familyId') required String familyId,
    @Body() required InvitationCreateInvitationRequest body,
  });

  /// Removes the Invitation resource.
  ///
  /// Removes the Invitation resource.
  ///
  /// [id] - Invitation identifier.
  @DELETE('/api/invitations/{id}')
  Future<void> apiInvitationsIdDelete({
    @Path('id') required String id,
  });
}
