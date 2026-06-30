import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';
import '../../family/application/family_context.dart';

/// Accès au planning de garde de la famille active (Lot 3). Le scope `X-Family-Id`
/// est posé par l'intercepteur réseau. La fenêtre [from, to] des jours/stats n'est
/// pas paramétrée ici : le backend renvoie le **mois courant** par défaut.
class CustodyRepository {
  CustodyRepository(this._clients);

  final ApiClients _clients;

  /// Rythme de garde, ou `null` si aucun n'est configuré (404).
  Future<api.CustodyPattern?> getPattern(String familyId) async {
    try {
      return await _clients.custodyPattern.apiFamiliesFamilyIdcustodypatternGet(
        familyId: familyId,
      );
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) return null;
      throw ApiException.fromDio(e);
    }
  }

  /// Jours effectifs du mois courant (base / vacances / exception).
  Future<List<api.CustodyDay>> listDays(String familyId) async {
    try {
      return await _clients.custodyDays
          .apiFamiliesFamilyIdcustodydaysGetCollection(familyId: familyId);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<api.CustodyStats> getStats(String familyId) async {
    try {
      return await _clients.custodyStats.apiFamiliesFamilyIdcustodystatsGet(
        familyId: familyId,
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<api.CustodyException>> listExceptions(String familyId) async {
    try {
      return await _clients.custodyExceptions
          .apiFamiliesFamilyIdcustodyexceptionsGetCollection(
            familyId: familyId,
          );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-CUST-4 — crée une exception (priorité maximale). « Échanger ce jour »
  /// passe par ici avec `startDate == endDate`.
  Future<api.CustodyException> createException(
    String familyId, {
    required String startDate,
    required String endDate,
    required String parentMembershipId,
    String? reason,
  }) async {
    try {
      return await _clients.custodyExceptions
          .apiFamiliesFamilyIdcustodyexceptionsPost(
            familyId: familyId,
            body: api.CustodyExceptionCreateExceptionRequest(
              startDate: startDate,
              endDate: endDate,
              parentMembershipId: parentMembershipId,
              reason: reason,
            ),
          );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> deleteException(String familyId, String exceptionId) async {
    try {
      await _clients.custodyExceptions
          .apiFamiliesFamilyIdcustodyexceptionsExceptionIdDelete(
            familyId: familyId,
            exceptionId: exceptionId,
          );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-CUST-1/2/9 — crée/modifie le rythme (régénération asynchrone côté serveur).
  Future<api.CustodyPattern> updatePattern(
    String familyId, {
    required String kind,
    required String anchorDate,
    required int handoverWeekday,
    required String handoverTime,
    required String parentAMembershipId,
    required String parentBMembershipId,
    List<String>? customPattern,
  }) async {
    try {
      return await _clients.custodyPattern.apiFamiliesFamilyIdcustodypatternPut(
        familyId: familyId,
        body: api.CustodyPatternUpdatePatternRequest(
          kind: kind,
          anchorDate: anchorDate,
          handoverWeekday: handoverWeekday,
          handoverTime: handoverTime,
          parentAMembershipId: parentAMembershipId,
          parentBMembershipId: parentBMembershipId,
          customPattern: customPattern,
        ),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<api.SchoolHoliday>> listHolidays(String familyId) async {
    try {
      return await _clients.holidays.apiFamiliesFamilyIdholidaysGetCollection(
        familyId: familyId,
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-CUST-7 — importe les vacances scolaires officielles (zone de la famille).
  Future<void> importHolidays(String familyId) async {
    try {
      await _clients.holidays.apiFamiliesFamilyIdholidaysimportPost(
        familyId: familyId,
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

final custodyRepositoryProvider = Provider<CustodyRepository>(
  (ref) => CustodyRepository(ref.watch(apiClientsProvider)),
);

/// Rythme de la famille active (recalculé à la bascule de famille).
final familyCustodyPatternProvider = FutureProvider<api.CustodyPattern?>((
  ref,
) async {
  final family = ref.watch(familyContextProvider);
  if (family == null || family.isIntact) return null;
  return ref.watch(custodyRepositoryProvider).getPattern(family.familyId);
});

final familyCustodyDaysProvider = FutureProvider<List<api.CustodyDay>>((
  ref,
) async {
  final family = ref.watch(familyContextProvider);
  if (family == null || family.isIntact) return const [];
  return ref.watch(custodyRepositoryProvider).listDays(family.familyId);
});

final familyCustodyStatsProvider = FutureProvider<api.CustodyStats?>((
  ref,
) async {
  final family = ref.watch(familyContextProvider);
  if (family == null || family.isIntact) return null;
  return ref.watch(custodyRepositoryProvider).getStats(family.familyId);
});

final familyCustodyExceptionsProvider =
    FutureProvider<List<api.CustodyException>>((ref) async {
      final family = ref.watch(familyContextProvider);
      if (family == null || family.isIntact) return const [];
      return ref
          .watch(custodyRepositoryProvider)
          .listExceptions(family.familyId);
    });

final familyHolidaysProvider = FutureProvider<List<api.SchoolHoliday>>((
  ref,
) async {
  final family = ref.watch(familyContextProvider);
  if (family == null || family.isIntact) return const [];
  return ref.watch(custodyRepositoryProvider).listHolidays(family.familyId);
});
