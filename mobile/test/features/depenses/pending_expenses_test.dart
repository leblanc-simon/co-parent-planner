import 'dart:convert';

import 'package:coparentplanner/core/storage/drift/database.dart';
import 'package:coparentplanner/core/sync/outbox_status.dart';
import 'package:coparentplanner/core/sync/pending_local.dart';
import 'package:coparentplanner/features/depenses/data/pending_expenses.dart';
import 'package:flutter_test/flutter_test.dart';

OutboxEntry entry({
  required String path,
  String? body,
  String method = 'POST',
  String id = 'o-1',
}) => OutboxEntry(
  id: id,
  userId: 'u-1',
  method: method,
  path: path,
  body: body,
  headers: null,
  idempotencyKey: 'k-$id',
  createdAt: DateTime(2026, 9, 4),
  status: OutboxStatus.pending,
  attempts: 0,
  lastError: null,
);

String bodyOf({
  String title = 'Cantine',
  String category = 'scolarite',
  int amountCents = 4250,
  String date = '2026-09-04',
}) => jsonEncode({
  'title': title,
  'category': category,
  'payerMembershipId': 'mA',
  'amountCents': amountCents,
  'expenseDate': date,
  'childIds': ['c-1'],
});

/// Reconstitution des dépenses saisies hors-ligne depuis l'outbox (US-DEP-8).
void main() {
  test('une création en file devient une dépense affichable', () {
    final expenses = pendingExpensesFrom([
      entry(path: '/api/families/fam-1/expenses', body: bodyOf()),
    ], familyId: 'fam-1');

    expect(expenses, hasLength(1));
    expect(expenses.single.title, 'Cantine');
    expect(expenses.single.amountCents, 4250);
    expect(expenses.single.expenseDate, '2026-09-04');
    expect(expenses.single.childIds, ['c-1']);
    // Marqueur d'attente : l'écran n'offrira ni règlement ni relance dessus.
    expect(PendingLocal.isPending(expenses.single.id), isTrue);
    // Ce que l'entité optimiste n'invente pas : elle n'a pas le nom du payeur.
    expect(expenses.single.payerName, isEmpty);
    expect(expenses.single.settled, isFalse);
  });

  test('seules les créations de CETTE famille sont reprises', () {
    final expenses = pendingExpensesFrom([
      entry(path: '/api/families/fam-2/expenses', body: bodyOf()),
    ], familyId: 'fam-1');

    expect(expenses, isEmpty);
  });

  test("une autre mutation de la même famille n'est pas une dépense", () {
    final expenses = pendingExpensesFrom([
      entry(path: '/api/families/fam-1/events', body: bodyOf()),
      entry(
        path: '/api/families/fam-1/expenses',
        body: bodyOf(),
        method: 'DELETE',
        id: 'o-2',
      ),
    ], familyId: 'fam-1');

    expect(expenses, isEmpty);
  });

  test('une entrée illisible est ignorée, jamais fatale', () {
    final expenses = pendingExpensesFrom([
      entry(path: '/api/families/fam-1/expenses', body: '{pas du json'),
      entry(
        path: '/api/families/fam-1/expenses',
        body: bodyOf(title: 'Manteau'),
        id: 'o-2',
      ),
    ], familyId: 'fam-1');

    expect(expenses.map((e) => e.title), ['Manteau']);
  });

  test('un corps sans montant ne fabrique pas un montant fantaisiste', () {
    final expenses = pendingExpensesFrom([
      entry(
        path: '/api/families/fam-1/expenses',
        body: jsonEncode({'title': 'X'}),
      ),
    ], familyId: 'fam-1');

    expect(expenses.single.amountCents, 0);
  });
}
