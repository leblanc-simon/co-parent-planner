// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/rgpd_operation_log.dart';

part 'rgpd_operation_log_client.g.dart';

@RestApi()
abstract class RgpdOperationLogClient {
  factory RgpdOperationLogClient(Dio dio, {String? baseUrl}) = _RgpdOperationLogClient;

  /// Retrieves the collection of RgpdOperationLog resources.
  ///
  /// Retrieves the collection of RgpdOperationLog resources.
  ///
  /// [type] - Filtre de type d'opération RGPD.
  ///
  /// [status] - Filtre de statut.
  ///
  /// [page] - Page (1 par défaut).
  ///
  /// [itemsPerPage] - Taille de page (50 par défaut, 200 maximum).
  @GET('/api/admin/rgpd/operations')
  Future<List<RgpdOperationLog>> apiAdminrgpdoperationsGetCollection({
    @Query('page') int? page = 1,
    @Query('itemsPerPage') int? itemsPerPage = 50,
    @Query('type') String? type,
    @Query('status') String? status,
  });
}
