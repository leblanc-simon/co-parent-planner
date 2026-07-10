// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/rgpd_admin.dart';

part 'rgpd_admin_client.g.dart';

@RestApi()
abstract class RgpdAdminClient {
  factory RgpdAdminClient(Dio dio, {String? baseUrl}) = _RgpdAdminClient;

  /// Retrieves the collection of RgpdAdmin resources.
  ///
  /// Retrieves the collection of RgpdAdmin resources.
  @GET('/api/admin/rgpd/compliance-report')
  Future<void> apiAdminrgpdcomplianceReportGetCollection();

  /// Retrieves the collection of RgpdAdmin resources.
  ///
  /// Retrieves the collection of RgpdAdmin resources.
  @GET('/api/admin/rgpd/operations')
  Future<void> apiAdminrgpdoperationsGetCollection();

  /// Creates a RgpdAdmin resource.
  ///
  /// Creates a RgpdAdmin resource.
  @POST('/api/admin/rgpd/purge')
  Future<void> apiAdminrgpdpurgePost({@Body() required RgpdAdmin body});

  /// Retrieves the collection of RgpdAdmin resources.
  ///
  /// Retrieves the collection of RgpdAdmin resources.
  @GET('/api/admin/rgpd/register')
  Future<void> apiAdminrgpdregisterGetCollection();
}
