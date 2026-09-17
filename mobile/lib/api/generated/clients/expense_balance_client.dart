// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/expense_balance.dart';

part 'expense_balance_client.g.dart';

@RestApi()
abstract class ExpenseBalanceClient {
  factory ExpenseBalanceClient(Dio dio, {String? baseUrl}) = _ExpenseBalanceClient;

  /// Retrieves a ExpenseBalance resource.
  ///
  /// Retrieves a ExpenseBalance resource.
  ///
  /// [familyId] - FamilyResource identifier.
  @GET('/api/families/{familyId}/expenses/balance')
  Future<ExpenseBalance> apiFamiliesFamilyIdexpensesbalanceGet({
    @Path('familyId') required String familyId,
  });
}
