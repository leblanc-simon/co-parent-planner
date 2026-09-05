// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/custody_color.dart';
import '../models/custody_color_update_custody_color_request.dart';

part 'custody_color_client.g.dart';

@RestApi()
abstract class CustodyColorClient {
  factory CustodyColorClient(Dio dio, {String? baseUrl}) = _CustodyColorClient;

  /// Retrieves the collection of CustodyColor resources.
  ///
  /// Retrieves the collection of CustodyColor resources.
  ///
  /// [familyId] - FamilyResource identifier.
  @GET('/api/families/{familyId}/custody-colors')
  Future<List<CustodyColor>> apiFamiliesFamilyIdcustodyColorsGetCollection({
    @Path('familyId') required String familyId,
  });

  /// Replaces the CustodyColor resource.
  ///
  /// Replaces the CustodyColor resource.
  ///
  /// [familyId] - FamilyResource identifier.
  @PUT('/api/families/{familyId}/custody-colors')
  Future<CustodyColor> apiFamiliesFamilyIdcustodyColorsPut({
    @Path('familyId') required String familyId,
    @Body() required CustodyColorUpdateCustodyColorRequest body,
  });
}
