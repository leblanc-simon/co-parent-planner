import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';
import '../../../core/session/session_scope.dart';
import '../../../core/sync/read_cache.dart';
import '../../family/application/family_context.dart';

/// Accès aux échanges (Lot 6) de la famille active. Le scope `X-Family-Id` est
/// posé par l'intercepteur réseau.
///
/// Deux contraintes assumées, cohérentes avec l'agenda (Lot 4) :
///  - **Filtre de statut client** : le client généré `GET /families/{id}/exchanges`
///    n'expose pas le paramètre `?status=` ; on récupère donc la collection complète
///    et on filtre/trie côté client (cf. [sortExchanges] / [filterByStatus]).
///  - **Temps réel différé** : pas d'infra SSE mobile (comme aux Lots 3/4) — le
///    rafraîchissement se fait par pull-to-refresh et par invalidation après action.
///    Le backend émet déjà les événements Mercure ; l'abonnement mobile est reporté.
class ExchangesRepository {
  ExchangesRepository(this._clients, this._cache);

  final ApiClients _clients;
  final ReadCache _cache;

  /// US-EX-4 — liste des échanges de la famille (résumés, sans fil). Repli **cache local**
  /// cloisonné famille hors-ligne (Lot 8 §2.2, US-OFF-1).
  Future<List<api.Exchange>> list(String userId, String familyId) async {
    final result = await _cache.list<api.Exchange>(
      userId: userId,
      familyId: familyId,
      endpoint: 'exchanges',
      networkCall: () => _clients.exchanges
          .apiFamiliesFamilyIdexchangesGetCollection(familyId: familyId),
      toJson: (e) => e.toJson(),
      fromJson: api.Exchange.fromJson,
    );
    return result.items;
  }

  /// US-EX-5 — détail d'un échange (options + décomptes + fil en clair).
  Future<api.Exchange> detail(String exchangeId) async {
    try {
      return await _clients.exchanges.apiExchangesIdGet(id: exchangeId);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-EX-1 — crée une discussion ou une décision. Pour une décision, [options]
  /// porte des paires `{label, sub?, position}` (≥ 2, validées serveur).
  Future<api.Exchange> create(
    String familyId, {
    required String type,
    required String title,
    String? body,
    String? childId,
    String? deadline,
    List<({String label, String? sub})>? options,
  }) async {
    try {
      return await _clients.exchanges.apiFamiliesFamilyIdexchangesPost(
        familyId: familyId,
        body: api.ExchangeCreateExchangeRequest(
          type: type,
          title: title,
          body: body,
          childId: childId,
          deadline: deadline,
          options: options == null
              ? null
              : [
                  for (var i = 0; i < options.length; i++)
                    <String, dynamic>{
                      'label': options[i].label,
                      if (options[i].sub != null && options[i].sub!.isNotEmpty)
                        'sub': options[i].sub,
                      'position': i,
                    },
                ],
        ),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-EX-2 — poste un message dans le fil (texte chiffré au repos côté serveur).
  Future<api.Message> postMessage(String exchangeId, String text) async {
    try {
      return await _clients.messages.apiExchangesExchangeIdmessagesPost(
        exchangeId: exchangeId,
        body: api.MessageCreateMessageRequest(text: text),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-EX-3 — valide une option d'une décision. Un `409` (décision déjà tranchée)
  /// remonte tel quel via [ApiException.statusCode] pour réalignement client.
  Future<api.Exchange> decide(String exchangeId, String optionId) async {
    try {
      return await _clients.exchanges.apiExchangesIddecidePost(
        id: exchangeId,
        body: api.ExchangeDecideRequest(optionId: optionId),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-EX-6 — clôture / édition d'un échange (statut, option retenue, échéance).
  Future<api.Exchange> patch(
    String exchangeId, {
    String? status,
    String? decidedOptionId,
    String? deadline,
  }) async {
    try {
      return await _clients.exchanges.apiExchangesIdPatch(
        id: exchangeId,
        body: api.ExchangeUpdateExchangeRequestJsonMergePatch(
          status: status,
          decidedOptionId: decidedOptionId,
          deadline: deadline,
        ),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

/// Restreint une liste au statut donné (`''` = tous). Filtrage client (le client
/// généré n'expose pas `?status=`).
List<api.Exchange> filterByStatus(List<api.Exchange> items, String status) =>
    status.isEmpty ? items : items.where((e) => e.status == status).toList();

/// Tri par défaut (§3.6) : ouverts d'abord, puis échéance la plus proche en tête
/// (`deadline` nulle en dernier), puis date de création décroissante. Pur et
/// testable.
List<api.Exchange> sortExchanges(List<api.Exchange> items) {
  final sorted = [...items];
  sorted.sort((a, b) {
    // 1) ouverts avant décidés.
    final aOpen = a.status == 'open';
    final bOpen = b.status == 'open';
    if (aOpen != bOpen) return aOpen ? -1 : 1;
    // 2) échéance la plus proche en tête ; nulle en dernier.
    final ad = a.deadline;
    final bd = b.deadline;
    if (ad != bd) {
      if (ad == null) return 1;
      if (bd == null) return -1;
      final byDeadline = ad.compareTo(bd);
      if (byDeadline != 0) return byDeadline;
    }
    // 3) création décroissante (plus récent d'abord).
    return b.createdAt.compareTo(a.createdAt);
  });
  return sorted;
}

/// Nombre d'échanges ouverts (badge, US-EX-7).
int openCount(List<api.Exchange> items) =>
    items.where((e) => e.status == 'open').length;

final exchangesRepositoryProvider = Provider<ExchangesRepository>(
  (ref) => ExchangesRepository(
    ref.watch(apiClientsProvider),
    ref.watch(readCacheProvider),
  ),
);

/// Échanges de la famille active (recalculés à la bascule de famille).
final familyExchangesProvider = FutureProvider<List<api.Exchange>>((ref) async {
  final family = ref.watch(familyContextProvider);
  final userId = ref.watch(currentUserIdProvider);
  if (family == null || userId == null) return const [];
  return ref.watch(exchangesRepositoryProvider).list(userId, family.familyId);
});

/// Détail d'un échange (fil + options), rechargé après chaque action.
final exchangeDetailProvider = FutureProvider.family<api.Exchange, String>((
  ref,
  exchangeId,
) async {
  ref.watch(currentUserIdProvider); // recalcul à la bascule de compte (C22)
  return ref.watch(exchangesRepositoryProvider).detail(exchangeId);
});
