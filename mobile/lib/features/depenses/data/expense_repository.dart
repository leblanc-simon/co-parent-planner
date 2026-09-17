import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';
import '../../../core/session/session_scope.dart';
import '../../../core/sync/idempotency_scope.dart';
import '../../../core/sync/outbox_queued_exception.dart';
import '../../../core/sync/outbox_service.dart';
import '../../../core/sync/read_cache.dart';
import '../../family/application/family_context.dart';
import 'pending_expenses.dart';

/// Accès aux dépenses partagées de la famille active (Lot 10). Le scope `X-Family-Id`
/// est posé par l'intercepteur réseau.
///
/// Le module n'existe pour une famille que si elle l'a activé : côté serveur toutes ces
/// routes répondent alors 404 (§3.4). Côté mobile, l'onglet n'est pas rendu dans ce cas —
/// le droit `expenses` renvoyé par l'API vaut déjà `none` quand le module est éteint, si
/// bien qu'aucune condition supplémentaire n'est à tenir ici.
class ExpenseRepository {
  ExpenseRepository(this._clients, this._cache, this._outbox);

  final ApiClients _clients;
  final ReadCache _cache;
  final OutboxService _outbox;

  /// Dépenses de la famille. En ligne : réseau + mise en cache ; hors-ligne : repli sur le
  /// cache local cloisonné compte/famille (US-OFF-1), enrichi des saisies encore en file.
  Future<CachedResult<api.Expense>> listExpenses(
    String userId,
    String familyId,
  ) async {
    final result = await _cache.list<api.Expense>(
      userId: userId,
      familyId: familyId,
      endpoint: 'expenses',
      networkCall: () => _clients.expenses
          .apiFamiliesFamilyIdexpensesGetCollection(familyId: familyId),
      toJson: (e) => e.toJson(),
      fromJson: api.Expense.fromJson,
    );

    final pending = pendingExpensesFrom(
      await _outbox.localEntries(userId),
      familyId: familyId,
    );
    if (pending.isEmpty) return result;

    return CachedResult<api.Expense>(
      [...pending, ...result.items],
      stale: result.stale,
      // Une saisie locale est quelque chose à montrer : hors-ligne et sans cache, l'écran
      // doit l'afficher plutôt que l'état « données non disponibles ».
      available: true,
    );
  }

  /// Solde « qui doit quoi » + agrégats. **Pas de repli cache** : un solde périmé est pire
  /// qu'un solde absent — il se lit comme une dette due, et deux parents n'ont pas à
  /// négocier sur un chiffre calculé avant la dernière saisie. Hors-ligne, l'écran affiche
  /// donc la liste (potentiellement en cache) sans le rail de solde.
  Future<api.ExpenseBalance> balance(String familyId) async {
    try {
      return await _clients.expenseBalance
          .apiFamiliesFamilyIdexpensesbalanceGet(familyId: familyId);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-DEP-1 — crée une dépense. Hors-ligne (US-DEP-8) : la mutation est mise en file dans
  /// l'outbox (Idempotency-Key + X-Family-Id figés) et rejouée au retour réseau ; l'appelant
  /// reçoit [OutboxQueuedException] (succès différé).
  Future<api.Expense> createExpense(
    String userId,
    String familyId, {
    required String title,
    required String category,
    required String payerMembershipId,
    required int amountCents,
    required String expenseDate,
    required List<String> childIds,
    List<api.ShareInput>? shares,
  }) async {
    final request = api.ExpenseCreateExpenseRequest(
      title: title,
      category: category,
      payerMembershipId: payerMembershipId,
      amountCents: amountCents,
      expenseDate: expenseDate,
      childIds: childIds,
      shares: shares,
    );
    // La clé est tirée AVANT la tentative en ligne et réutilisée par l'entrée outbox : si la
    // requête expire après traitement serveur, le rejeu est dédoublonné (§6.3) au lieu de
    // créer une seconde dépense — donc un solde faux.
    final idempotencyKey = const Uuid().v4();
    try {
      return await IdempotencyScope.run(
        idempotencyKey,
        () => _clients.expenses.apiFamiliesFamilyIdexpensesPost(
          familyId: familyId,
          body: request,
        ),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        throw ApiException.fromDio(
          e,
        ); // erreur métier en ligne → remontée normale
      }
      await _outbox.enqueue(
        userId: userId,
        method: 'POST',
        path: expensesCreatePath(familyId),
        body: request.toJson(),
        xFamilyId: familyId,
        idempotencyKey: idempotencyKey,
      );
      throw const OutboxQueuedException();
    }
  }

  /// US-DEP-2 — marque une dépense réglée. Idempotent côté serveur : rejouer l'appel ne
  /// produit ni second changement d'état ni seconde notification.
  Future<api.Expense> settle(String expenseId) async {
    try {
      return await _clients.expenses.apiExpensesIdPatch(
        id: expenseId,
        body: const api.ExpenseUpdateExpenseRequestJsonMergePatch(
          settled: true,
        ),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-DEP-2 — relance le(s) parent(s) débiteur(s). Le serveur refuse (429) tant que le
  /// cooldown court : l'écran s'appuie sur `reminderAvailableAt` pour ne pas proposer un
  /// bouton qui échouera.
  Future<api.Expense> remind(String expenseId) async {
    try {
      return await _clients.expenses.apiExpensesIdreminderPost(id: expenseId);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-DEP-5 — supprime une dépense saisie par erreur.
  Future<void> deleteExpense(String expenseId) async {
    try {
      await _clients.expenses.apiExpensesIdDelete(id: expenseId);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

final expenseRepositoryProvider = Provider<ExpenseRepository>(
  (ref) => ExpenseRepository(
    ref.watch(apiClientsProvider),
    ref.watch(readCacheProvider),
    ref.watch(outboxServiceProvider),
  ),
);

/// Dépenses de la famille active.
final familyExpensesProvider = FutureProvider<CachedResult<api.Expense>>((
  ref,
) async {
  final family = ref.watch(familyContextProvider);
  final userId = ref.watch(currentUserIdProvider);
  if (family == null || userId == null) {
    return const CachedResult<api.Expense>.fresh([]);
  }

  return ref
      .watch(expenseRepositoryProvider)
      .listExpenses(userId, family.familyId);
});

/// Solde de la famille active. `null` quand il n'est pas lisible (hors-ligne) : l'écran
/// masque alors le rail plutôt que d'afficher un montant qu'il ne peut pas garantir.
final expenseBalanceProvider = FutureProvider<api.ExpenseBalance?>((ref) async {
  final family = ref.watch(familyContextProvider);
  if (family == null) return null;
  try {
    return await ref.watch(expenseRepositoryProvider).balance(family.familyId);
  } on ApiException {
    return null;
  }
});
