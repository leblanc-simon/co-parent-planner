import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';
import '../../../core/session/session_scope.dart';
import '../../../core/sync/read_cache.dart';
import '../../../core/util/civil_date.dart';
import '../../family/application/family_context.dart';
import '../../members/data/members_repository.dart';

/// Accès au planning de garde de la famille active (Lot 3). Le scope `X-Family-Id`
/// est posé par l'intercepteur réseau.
///
/// La fenêtre `[from, to]` des jours et des statistiques est **explicite** : sans
/// elle le serveur retombe sur le mois civil courant, si bien que les deux chevrons
/// de l'écran ne pouvaient rien faire d'autre que d'être inertes (revue mobile
/// 2026-08, constat C27).
class CustodyRepository {
  CustodyRepository(this._clients, this._cache);

  final ApiClients _clients;
  final ReadCache _cache;

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

  /// Jours effectifs de la fenêtre demandée (base / vacances / exception).
  Future<List<api.CustodyDay>> listDays(
    String familyId, {
    required DateTime from,
    required DateTime to,
  }) async {
    try {
      return await _clients.custodyDays
          .apiFamiliesFamilyIdcustodydaysGetCollection(
            familyId: familyId,
            from: CivilDate.format(from),
            to: CivilDate.format(to),
          );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// Statistiques de la **même** fenêtre que les jours affichés : les faire porter
  /// sur deux périodes différentes serait pire qu'une navigation absente.
  Future<api.CustodyStats> getStats(
    String familyId, {
    required DateTime from,
    required DateTime to,
  }) async {
    try {
      return await _clients.custodyStats.apiFamiliesFamilyIdcustodystatsGet(
        familyId: familyId,
        from: CivilDate.format(from),
        to: CivilDate.format(to),
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

  /// US-CUST-4 — crée une exception (priorité maximale). « Échanger ce jour » passe par ici avec
  /// le créneau choisi : les bornes peuvent déborder sur la veille ou le lendemain (cdc §3.4).
  Future<api.CustodyException> createException(
    String familyId, {
    required String startDate,
    required String endDate,
    required String parentMembershipId,
    String? startTime,
    String? endTime,
    String? reason,
  }) async {
    try {
      return await _clients.custodyExceptions
          .apiFamiliesFamilyIdcustodyexceptionsPost(
            familyId: familyId,
            body: api.CustodyExceptionCreateExceptionRequest(
              startDate: startDate,
              endDate: endDate,
              // Heures absentes = journées entières : la forme d'avant qu'elles existent.
              startTime: startTime,
              endTime: endTime,
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
    String? dayStartTime,
    required String parentAMembershipId,
    required String parentBMembershipId,
    List<String>? customPattern,
    Map<String, Object?>? settings,
  }) async {
    try {
      return await _clients.custodyPattern.apiFamiliesFamilyIdcustodypatternPut(
        familyId: familyId,
        body: api.CustodyPatternUpdatePatternRequest(
          kind: kind,
          anchorDate: anchorDate,
          handoverWeekday: handoverWeekday,
          handoverTime: handoverTime,
          dayStartTime: dayStartTime,
          parentAMembershipId: parentAMembershipId,
          parentBMembershipId: parentBMembershipId,
          customPattern: customPattern,
          // `settings` est requis pour `midweek_weekends` et **interdit** ailleurs
          // (`custody.error.config_conflict`) : l'écran ne l'envoie que pour ce
          // modèle, et le serveur refusait toute sauvegarde sans lui (C26).
          settings: settings,
        ),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// Couleurs du planning choisies par le **membre courant** (cdc §8.2, évolution E5).
  ///
  /// Lecture avec repli **cache local** : sans lui, le planning changerait de couleurs en mode
  /// avion — c'est-à-dire au moment où l'utilisateur a le moins de moyens de comprendre pourquoi.
  Future<List<api.CustodyColor>> listColors(
    String userId,
    String familyId,
  ) async {
    final result = await _cache.list<api.CustodyColor>(
      userId: userId,
      familyId: familyId,
      endpoint: 'custody-colors',
      networkCall: () => _clients.custodyColors
          .apiFamiliesFamilyIdcustodyColorsGetCollection(familyId: familyId),
      toJson: (c) => c.toJson(),
      fromJson: api.CustodyColor.fromJson,
    );

    return result.items;
  }

  /// Upsert idempotent d'une couleur : `custody ≥ view` suffit, la préférence n'engageant que
  /// l'affichage de son auteur.
  Future<api.CustodyColor> setColor(
    String familyId, {
    required String targetMembershipId,
    required String palette,
  }) async {
    try {
      return await _clients.custodyColors.apiFamiliesFamilyIdcustodyColorsPut(
        familyId: familyId,
        body: api.CustodyColorUpdateCustodyColorRequest(
          targetMembershipId: targetMembershipId,
          palette: api.CustodyColorUpdateCustodyColorRequestPalette.fromJson(
            palette,
          ),
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
  (ref) => CustodyRepository(
    ref.watch(apiClientsProvider),
    ref.watch(readCacheProvider),
  ),
);

/// Rythme de la famille active (recalculé à la bascule de famille).
final familyCustodyPatternProvider = FutureProvider<api.CustodyPattern?>((
  ref,
) async {
  final family = ref.watch(familyContextProvider);
  if (family == null || family.isIntact) return null;
  return ref.watch(custodyRepositoryProvider).getPattern(family.familyId);
});

/// Préférences de couleur du membre courant pour la famille active (cdc §8.2).
///
/// Recalculé à la bascule de compte comme de famille : une préférence est propre au couple
/// (spectateur, famille), et rejouer celle du compte précédent peindrait le mauvais planning.
final custodyColorsProvider = FutureProvider<List<api.CustodyColor>>((
  ref,
) async {
  final family = ref.watch(familyContextProvider);
  final userId = ref.watch(currentUserIdProvider);
  if (family == null || userId == null || family.isIntact) return const [];

  return ref
      .watch(custodyRepositoryProvider)
      .listColors(userId, family.familyId);
});

/// Mois affiché par l'écran Garde (US-CUST-5, « navigation période »).
///
/// Distinct du mois de l'agenda : les deux écrans se naviguent séparément, et
/// partager le même état ferait bouger l'un en naviguant dans l'autre.
final custodyMonthProvider = NotifierProvider<CustodyMonthController, DateTime>(
  CustodyMonthController.new,
);

class CustodyMonthController extends Notifier<DateTime> {
  @override
  DateTime build() {
    final now = DateTime.now();
    return DateTime(now.year, now.month);
  }

  void shift(int months) {
    state = DateTime(state.year, state.month + months);
  }

  void reset() {
    final now = DateTime.now();
    state = DateTime(now.year, now.month);
  }
}

/// Premier et dernier jour du mois affiché (jour 0 du mois suivant = dernier du mois).
({DateTime from, DateTime to}) custodyWindow(DateTime month) => (
  from: DateTime(month.year, month.month),
  to: DateTime(month.year, month.month + 1, 0),
);

/// Fenêtre des **jours chargés** : le mois affiché plus la **veille du 1er**.
///
/// Cette veille ne s'affiche pas — les cases de complément de semaine restent grises. Elle porte
/// l'attributaire dont le 1er prend la suite, sans lequel un 1er tombant un jour d'échange se
/// peindrait comme une journée pleine. Les statistiques, elles, gardent [custodyWindow] : elles
/// comptent le mois, pas un jour de plus.
({DateTime from, DateTime to}) custodyDaysWindow(DateTime month) => (
  from: DateTime(month.year, month.month, 0),
  to: DateTime(month.year, month.month + 1, 0),
);

final familyCustodyDaysProvider = FutureProvider<List<api.CustodyDay>>((
  ref,
) async {
  final family = ref.watch(familyContextProvider);
  if (family == null || family.isIntact) return const [];
  final window = custodyDaysWindow(ref.watch(custodyMonthProvider));
  return ref
      .watch(custodyRepositoryProvider)
      .listDays(family.familyId, from: window.from, to: window.to);
});

final familyCustodyStatsProvider = FutureProvider<api.CustodyStats?>((
  ref,
) async {
  final family = ref.watch(familyContextProvider);
  if (family == null || family.isIntact) return null;
  final window = custodyWindow(ref.watch(custodyMonthProvider));
  return ref
      .watch(custodyRepositoryProvider)
      .getStats(family.familyId, from: window.from, to: window.to);
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

/// Droit `custody` **effectif** du membre courant.
///
/// L'écran Garde n'interrogeait aucun droit : « Échanger ce jour », la suppression
/// d'une exception, l'import des vacances et l'accès aux réglages étaient offerts à
/// un membre `custody=view`, qui n'en récoltait qu'un 403 (revue mobile 2026-08,
/// constat C29).
final custodyRightProvider = Provider<String?>(
  (ref) => ref.watch(rightProvider('custody')),
);

/// Vrai quand le membre courant peut **modifier** la garde.
final custodyCanEditProvider = Provider<bool>(
  (ref) => ref.watch(canEditProvider('custody')),
);

/// Vrai quand le membre courant peut consulter l'agenda de la famille : les activités
/// du planning de garde relèvent de ce domaine-là, pas de `custody`.
final agendaCanViewProvider = Provider<bool>(
  (ref) => ref.watch(canViewProvider('agenda')),
);
