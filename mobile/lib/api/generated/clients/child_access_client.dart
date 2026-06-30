// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/child_access.dart';

part 'child_access_client.g.dart';

@RestApi()
abstract class ChildAccessClient {
  factory ChildAccessClient(Dio dio, {String? baseUrl}) = _ChildAccessClient;

  /// Retrieves the collection of ChildAccess resources.
  ///
  /// Retrieves the collection of ChildAccess resources.
  ///
  /// [childId] - ChildResource identifier.
  @GET('/api/children/{childId}/access')
  Future<List<ChildAccess>> apiChildrenChildIdaccessGetCollection({
    @Path('childId') required String childId,
  });
}
