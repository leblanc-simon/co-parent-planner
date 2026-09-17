// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/category.dart';
import '../models/expense.dart';
import '../models/expense_create_expense_request.dart';
import '../models/expense_update_expense_request_json_merge_patch.dart';

part 'expense_client.g.dart';

@RestApi()
abstract class ExpenseClient {
  factory ExpenseClient(Dio dio, {String? baseUrl}) = _ExpenseClient;

  /// Removes the Expense resource.
  ///
  /// Removes the Expense resource.
  ///
  /// [id] - Expense identifier.
  @DELETE('/api/expenses/{id}')
  Future<void> apiExpensesIdDelete({
    @Path('id') required String id,
  });

  /// Updates the Expense resource.
  ///
  /// Updates the Expense resource.
  ///
  /// [id] - Expense identifier.
  @PATCH('/api/expenses/{id}')
  Future<Expense> apiExpensesIdPatch({
    @Path('id') required String id,
    @Body() required ExpenseUpdateExpenseRequestJsonMergePatch body,
  });

  /// Creates a Expense resource.
  ///
  /// Creates a Expense resource.
  ///
  /// [id] - Expense identifier.
  @POST('/api/expenses/{id}/reminder')
  Future<Expense> apiExpensesIdreminderPost({
    @Path('id') required String id,
  });

  /// Retrieves the collection of Expense resources.
  ///
  /// Retrieves the collection of Expense resources.
  ///
  /// [familyId] - FamilyResource identifier.
  ///
  /// [category] - Filtre de catégorie (`sante`, `scolarite`, `activites`, `vetements`, `autre`). Absent = toutes.
  ///
  /// [childId] - Ne retenir que les dépenses concernant cet enfant de la famille active.
  ///
  /// [settled] - Filtre d'état : `true` (réglées), `false` (non réglées). Absent = les deux.
  @GET('/api/families/{familyId}/expenses')
  Future<List<Expense>> apiFamiliesFamilyIdexpensesGetCollection({
    @Path('familyId') required String familyId,
    @Query('category') Category? category,
    @Query('childId') String? childId,
    @Query('settled') bool? settled,
  });

  /// Creates a Expense resource.
  ///
  /// Creates a Expense resource.
  ///
  /// [familyId] - FamilyResource identifier.
  @POST('/api/families/{familyId}/expenses')
  Future<Expense> apiFamiliesFamilyIdexpensesPost({
    @Path('familyId') required String familyId,
    @Body() required ExpenseCreateExpenseRequest body,
  });
}
