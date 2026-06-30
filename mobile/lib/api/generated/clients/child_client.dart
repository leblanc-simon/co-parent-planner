// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/child.dart';
import '../models/child_create_child_request.dart';
import '../models/child_update_child_request_json_merge_patch.dart';

part 'child_client.g.dart';

@RestApi()
abstract class ChildClient {
  factory ChildClient(Dio dio, {String? baseUrl}) = _ChildClient;

  /// Retrieves a Child resource.
  ///
  /// Retrieves a Child resource.
  ///
  /// [id] - Child identifier.
  @GET('/api/children/{id}')
  Future<Child> apiChildrenIdGet({
    @Path('id') required String id,
  });

  /// Removes the Child resource.
  ///
  /// Removes the Child resource.
  ///
  /// [id] - Child identifier.
  @DELETE('/api/children/{id}')
  Future<void> apiChildrenIdDelete({
    @Path('id') required String id,
  });

  /// Updates the Child resource.
  ///
  /// Updates the Child resource.
  ///
  /// [id] - Child identifier.
  @PATCH('/api/children/{id}')
  Future<Child> apiChildrenIdPatch({
    @Path('id') required String id,
    @Body() required ChildUpdateChildRequestJsonMergePatch body,
  });

  /// Retrieves the collection of Child resources.
  ///
  /// Retrieves the collection of Child resources.
  ///
  /// [familyId] - FamilyResource identifier.
  @GET('/api/families/{familyId}/children')
  Future<List<Child>> apiFamiliesFamilyIdchildrenGetCollection({
    @Path('familyId') required String familyId,
  });

  /// Creates a Child resource.
  ///
  /// Creates a Child resource.
  ///
  /// [familyId] - FamilyResource identifier.
  @POST('/api/families/{familyId}/children')
  Future<Child> apiFamiliesFamilyIdchildrenPost({
    @Path('familyId') required String familyId,
    @Body() required ChildCreateChildRequest body,
  });
}
