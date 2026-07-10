// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/me_me_read.dart';
import '../models/me_me_write_json_merge_patch.dart';

part 'me_client.g.dart';

@RestApi()
abstract class MeClient {
  factory MeClient(Dio dio, {String? baseUrl}) = _MeClient;

  /// Retrieves a Me resource.
  ///
  /// Retrieves a Me resource.
  @GET('/api/me')
  Future<MeMeRead> apiMeGet();

  /// Removes the Me resource.
  ///
  /// Removes the Me resource.
  @DELETE('/api/me')
  Future<void> apiMeDelete();

  /// Updates the Me resource.
  ///
  /// Updates the Me resource.
  @PATCH('/api/me')
  Future<MeMeRead> apiMePatch({
    @Body() required MeMeWriteJsonMergePatch body,
  });
}
