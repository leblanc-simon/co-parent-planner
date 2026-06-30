// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/school_holiday.dart';
import '../models/school_holiday_create_holiday_request.dart';
import '../models/school_holiday_update_rule_request.dart';

part 'school_holiday_client.g.dart';

@RestApi()
abstract class SchoolHolidayClient {
  factory SchoolHolidayClient(Dio dio, {String? baseUrl}) = _SchoolHolidayClient;

  /// Retrieves the collection of SchoolHoliday resources.
  ///
  /// Retrieves the collection of SchoolHoliday resources.
  ///
  /// [familyId] - FamilyResource identifier.
  @GET('/api/families/{familyId}/holidays')
  Future<List<SchoolHoliday>> apiFamiliesFamilyIdholidaysGetCollection({
    @Path('familyId') required String familyId,
  });

  /// Creates a SchoolHoliday resource.
  ///
  /// Creates a SchoolHoliday resource.
  ///
  /// [familyId] - FamilyResource identifier.
  @POST('/api/families/{familyId}/holidays')
  Future<SchoolHoliday> apiFamiliesFamilyIdholidaysPost({
    @Path('familyId') required String familyId,
    @Body() required SchoolHolidayCreateHolidayRequest body,
  });

  /// Creates a SchoolHoliday resource.
  ///
  /// Creates a SchoolHoliday resource.
  ///
  /// [familyId] - FamilyResource identifier.
  @POST('/api/families/{familyId}/holidays/import')
  Future<void> apiFamiliesFamilyIdholidaysimportPost({
    @Path('familyId') required String familyId,
  });

  /// Replaces the SchoolHoliday resource.
  ///
  /// Replaces the SchoolHoliday resource.
  ///
  /// [id] - SchoolHoliday identifier.
  @PUT('/api/holidays/{id}/rule')
  Future<SchoolHoliday> apiHolidaysIdrulePut({
    @Path('id') required String id,
    @Body() required SchoolHolidayUpdateRuleRequest body,
  });
}
