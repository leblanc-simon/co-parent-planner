// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/me_export.dart';

part 'me_export_client.g.dart';

@RestApi()
abstract class MeExportClient {
  factory MeExportClient(Dio dio, {String? baseUrl}) = _MeExportClient;

  /// Creates a MeExport resource.
  ///
  /// Creates a MeExport resource.
  @POST('/api/me/export')
  Future<MeExport> apiMeexportPost({
    @Body() required MeExport body,
  });

  /// Retrieves a MeExport resource.
  ///
  /// Retrieves a MeExport resource.
  ///
  /// [id] - MeExport identifier.
  @GET('/api/me/export/{id}')
  Future<MeExport> apiMeexportIdGet({
    @Path('id') required String id,
  });
}
