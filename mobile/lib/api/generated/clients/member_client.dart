// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/member.dart';
import '../models/member_update_membership_request_json_merge_patch.dart';

part 'member_client.g.dart';

@RestApi()
abstract class MemberClient {
  factory MemberClient(Dio dio, {String? baseUrl}) = _MemberClient;

  /// Retrieves the collection of Member resources.
  ///
  /// Retrieves the collection of Member resources.
  ///
  /// [familyId] - FamilyResource identifier.
  @GET('/api/families/{familyId}/members')
  Future<List<Member>> apiFamiliesFamilyIdmembersGetCollection({
    @Path('familyId') required String familyId,
  });

  /// Removes the Member resource.
  ///
  /// Removes the Member resource.
  ///
  /// [id] - Member identifier.
  @DELETE('/api/memberships/{id}')
  Future<void> apiMembershipsIdDelete({
    @Path('id') required String id,
  });

  /// Updates the Member resource.
  ///
  /// Updates the Member resource.
  ///
  /// [id] - Member identifier.
  @PATCH('/api/memberships/{id}')
  Future<Member> apiMembershipsIdPatch({
    @Path('id') required String id,
    @Body() required MemberUpdateMembershipRequestJsonMergePatch body,
  });
}
