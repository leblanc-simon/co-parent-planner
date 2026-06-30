// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/custody_exception.dart';
import '../models/custody_exception_create_exception_request.dart';

part 'custody_exception_client.g.dart';

@RestApi()
abstract class CustodyExceptionClient {
  factory CustodyExceptionClient(Dio dio, {String? baseUrl}) = _CustodyExceptionClient;

  /// Retrieves the collection of CustodyException resources.
  ///
  /// Retrieves the collection of CustodyException resources.
  ///
  /// [familyId] - FamilyResource identifier.
  @GET('/api/families/{familyId}/custody/exceptions')
  Future<List<CustodyException>> apiFamiliesFamilyIdcustodyexceptionsGetCollection({
    @Path('familyId') required String familyId,
  });

  /// Creates a CustodyException resource.
  ///
  /// Creates a CustodyException resource.
  ///
  /// [familyId] - FamilyResource identifier.
  @POST('/api/families/{familyId}/custody/exceptions')
  Future<CustodyException> apiFamiliesFamilyIdcustodyexceptionsPost({
    @Path('familyId') required String familyId,
    @Body() required CustodyExceptionCreateExceptionRequest body,
  });

  /// Removes the CustodyException resource.
  ///
  /// Removes the CustodyException resource.
  ///
  /// [familyId] - FamilyResource identifier.
  ///
  /// [exceptionId] - CustodyException identifier.
  @DELETE('/api/families/{familyId}/custody/exceptions/{exceptionId}')
  Future<void> apiFamiliesFamilyIdcustodyexceptionsExceptionIdDelete({
    @Path('familyId') required String familyId,
    @Path('exceptionId') required String exceptionId,
  });
}
