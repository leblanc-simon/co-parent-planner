// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/rgpd_purge.dart';

part 'rgpd_purge_client.g.dart';

@RestApi()
abstract class RgpdPurgeClient {
  factory RgpdPurgeClient(Dio dio, {String? baseUrl}) = _RgpdPurgeClient;

  /// Creates a RgpdPurge resource.
  ///
  /// Creates a RgpdPurge resource.
  @POST('/api/admin/rgpd/purge')
  Future<RgpdPurge> apiAdminrgpdpurgePost({
    @Body() required RgpdPurge body,
  });
}
