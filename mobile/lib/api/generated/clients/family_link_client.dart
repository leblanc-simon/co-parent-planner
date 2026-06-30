// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/family_link.dart';
import '../models/family_link_create_link_request.dart';

part 'family_link_client.g.dart';

@RestApi()
abstract class FamilyLinkClient {
  factory FamilyLinkClient(Dio dio, {String? baseUrl}) = _FamilyLinkClient;

  /// Retrieves the collection of FamilyLink resources.
  ///
  /// Retrieves the collection of FamilyLink resources.
  ///
  /// [familyId] - FamilyResource identifier.
  @GET('/api/families/{familyId}/links')
  Future<List<FamilyLink>> apiFamiliesFamilyIdlinksGetCollection({
    @Path('familyId') required String familyId,
  });

  /// Creates a FamilyLink resource.
  ///
  /// Creates a FamilyLink resource.
  ///
  /// [familyId] - FamilyResource identifier.
  @POST('/api/families/{familyId}/links')
  Future<FamilyLink> apiFamiliesFamilyIdlinksPost({
    @Path('familyId') required String familyId,
    @Body() required FamilyLinkCreateLinkRequest body,
  });

  /// Removes the FamilyLink resource.
  ///
  /// Removes the FamilyLink resource.
  ///
  /// [familyId] - FamilyResource identifier.
  ///
  /// [linkId] - FamilyLink identifier.
  @DELETE('/api/families/{familyId}/links/{linkId}')
  Future<void> apiFamiliesFamilyIdlinksLinkIdDelete({
    @Path('familyId') required String familyId,
    @Path('linkId') required String linkId,
  });

  /// Creates a FamilyLink resource.
  ///
  /// Creates a FamilyLink resource.
  ///
  /// [familyId] - FamilyResource identifier.
  ///
  /// [linkId] - FamilyLink identifier.
  @POST('/api/families/{familyId}/links/{linkId}/activate')
  Future<FamilyLink> apiFamiliesFamilyIdlinksLinkIdactivatePost({
    @Path('familyId') required String familyId,
    @Path('linkId') required String linkId,
    @Body() required FamilyLink body,
  });
}
