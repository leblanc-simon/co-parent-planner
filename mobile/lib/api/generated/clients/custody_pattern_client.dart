// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/custody_pattern.dart';
import '../models/custody_pattern_update_pattern_request.dart';

part 'custody_pattern_client.g.dart';

@RestApi()
abstract class CustodyPatternClient {
  factory CustodyPatternClient(Dio dio, {String? baseUrl}) = _CustodyPatternClient;

  /// Retrieves a CustodyPattern resource.
  ///
  /// Retrieves a CustodyPattern resource.
  ///
  /// [familyId] - FamilyResource identifier.
  @GET('/api/families/{familyId}/custody/pattern')
  Future<CustodyPattern> apiFamiliesFamilyIdcustodypatternGet({
    @Path('familyId') required String familyId,
  });

  /// Replaces the CustodyPattern resource.
  ///
  /// Replaces the CustodyPattern resource.
  ///
  /// [familyId] - FamilyResource identifier.
  @PUT('/api/families/{familyId}/custody/pattern')
  Future<CustodyPattern> apiFamiliesFamilyIdcustodypatternPut({
    @Path('familyId') required String familyId,
    @Body() required CustodyPatternUpdatePatternRequest body,
  });
}
