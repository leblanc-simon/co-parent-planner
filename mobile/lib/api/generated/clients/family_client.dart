// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/family.dart';
import '../models/family_create_family_request.dart';
import '../models/family_update_family_request_json_merge_patch.dart';

part 'family_client.g.dart';

@RestApi()
abstract class FamilyClient {
  factory FamilyClient(Dio dio, {String? baseUrl}) = _FamilyClient;

  /// Retrieves the collection of Family resources.
  ///
  /// Retrieves the collection of Family resources.
  @GET('/api/families')
  Future<List<Family>> apiFamiliesGetCollection();

  /// Creates a Family resource.
  ///
  /// Creates a Family resource.
  @POST('/api/families')
  Future<Family> apiFamiliesPost({
    @Body() required FamilyCreateFamilyRequest body,
  });

  /// Retrieves a Family resource.
  ///
  /// Retrieves a Family resource.
  ///
  /// [id] - Family identifier.
  @GET('/api/families/{id}')
  Future<Family> apiFamiliesIdGet({
    @Path('id') required String id,
  });

  /// Updates the Family resource.
  ///
  /// Updates the Family resource.
  ///
  /// [id] - Family identifier.
  @PATCH('/api/families/{id}')
  Future<Family> apiFamiliesIdPatch({
    @Path('id') required String id,
    @Body() required FamilyUpdateFamilyRequestJsonMergePatch body,
  });
}
