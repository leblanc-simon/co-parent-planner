// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/custody_stats.dart';

part 'custody_stats_client.g.dart';

@RestApi()
abstract class CustodyStatsClient {
  factory CustodyStatsClient(Dio dio, {String? baseUrl}) = _CustodyStatsClient;

  /// Retrieves a CustodyStats resource.
  ///
  /// Retrieves a CustodyStats resource.
  ///
  /// [familyId] - FamilyResource identifier.
  @GET('/api/families/{familyId}/custody/stats')
  Future<CustodyStats> apiFamiliesFamilyIdcustodystatsGet({
    @Path('familyId') required String familyId,
  });
}
