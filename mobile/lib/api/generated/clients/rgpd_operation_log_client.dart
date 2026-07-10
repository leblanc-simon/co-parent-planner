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
  @GET('/api/admin/rgpd/operations')
  Future<List<RgpdOperationLog>> apiAdminrgpdoperationsGetCollection();
}
