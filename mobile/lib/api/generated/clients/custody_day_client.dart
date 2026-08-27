// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/custody_day.dart';

part 'custody_day_client.g.dart';

@RestApi()
abstract class CustodyDayClient {
  factory CustodyDayClient(Dio dio, {String? baseUrl}) = _CustodyDayClient;

  /// Retrieves the collection of CustodyDay resources.
  ///
  /// Retrieves the collection of CustodyDay resources.
  ///
  /// [familyId] - FamilyResource identifier.
  ///
  /// [from] - Début de la fenêtre (date locale, AAAA-MM-JJ). Défaut : 1er du mois courant.
  ///
  /// [to] - Fin de la fenêtre, incluse (AAAA-MM-JJ). Défaut : dernier jour du mois courant.
  @GET('/api/families/{familyId}/custody/days')
  Future<List<CustodyDay>> apiFamiliesFamilyIdcustodydaysGetCollection({
    @Path('familyId') required String familyId,
    @Query('from') DateTime? from,
    @Query('to') DateTime? to,
  });
}
