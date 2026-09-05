// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/exchange.dart';
import '../models/exchange_create_exchange_request.dart';
import '../models/exchange_decide_request.dart';
import '../models/exchange_update_exchange_request_json_merge_patch.dart';
import '../models/status.dart';

part 'exchange_client.g.dart';

@RestApi()
abstract class ExchangeClient {
  factory ExchangeClient(Dio dio, {String? baseUrl}) = _ExchangeClient;

  /// Retrieves a Exchange resource.
  ///
  /// Retrieves a Exchange resource.
  ///
  /// [id] - Exchange identifier.
  @GET('/api/exchanges/{id}')
  Future<Exchange> apiExchangesIdGet({
    @Path('id') required String id,
  });

  /// Updates the Exchange resource.
  ///
  /// Updates the Exchange resource.
  ///
  /// [id] - Exchange identifier.
  @PATCH('/api/exchanges/{id}')
  Future<Exchange> apiExchangesIdPatch({
    @Path('id') required String id,
    @Body() required ExchangeUpdateExchangeRequestJsonMergePatch body,
  });

  /// Creates a Exchange resource.
  ///
  /// Creates a Exchange resource.
  ///
  /// [id] - Exchange identifier.
  @POST('/api/exchanges/{id}/decide')
  Future<Exchange> apiExchangesIddecidePost({
    @Path('id') required String id,
    @Body() required ExchangeDecideRequest body,
  });

  /// Creates a Exchange resource.
  ///
  /// Creates a Exchange resource.
  ///
  /// [id] - Exchange identifier.
  @POST('/api/exchanges/{id}/read')
  Future<Exchange> apiExchangesIdreadPost({
    @Path('id') required String id,
  });

  /// Retrieves the collection of Exchange resources.
  ///
  /// Retrieves the collection of Exchange resources.
  ///
  /// [familyId] - FamilyResource identifier.
  ///
  /// [status] - Filtre de statut : `open` ou `decided`. Absent = tous les échanges de la famille.
  @GET('/api/families/{familyId}/exchanges')
  Future<List<Exchange>> apiFamiliesFamilyIdexchangesGetCollection({
    @Path('familyId') required String familyId,
    @Query('status') Status? status,
  });

  /// Creates a Exchange resource.
  ///
  /// Creates a Exchange resource.
  ///
  /// [familyId] - FamilyResource identifier.
  @POST('/api/families/{familyId}/exchanges')
  Future<Exchange> apiFamiliesFamilyIdexchangesPost({
    @Path('familyId') required String familyId,
    @Body() required ExchangeCreateExchangeRequest body,
  });
}
