import 'dart:convert';

import '../../../api/generated/export.dart' as api;
import '../../../core/storage/drift/database.dart';
import '../../../core/sync/pending_local.dart';

/// Chemin des créations de dépense mises en file (US-DEP-8 hors-ligne).
String expensesCreatePath(String familyId) =>
    '/api/families/$familyId/expenses';

/// Dépenses **créées hors-ligne**, reconstituées depuis les entrées outbox encore locales.
///
/// Sans cette fusion, une dépense saisie sans réseau n'apparaîtrait sur aucun écran : ni le
/// serveur ni le cache ne la connaissent, et l'utilisateur croirait sa saisie perdue.
///
/// Ce que l'entité optimiste **n'invente pas** : le nom du payeur (le corps ne porte qu'un
/// identifiant de membership) et le montant effectif de chaque part, qui est un calcul serveur.
/// L'identifiant est synthétique (`pending:<entrée outbox>`), ce qui permet à l'écran de marquer la
/// ligne « en attente de synchro » et de ne pas lui proposer d'actions que le serveur refuserait —
/// régler ou relancer une dépense qu'il ne connaît pas encore.
List<api.Expense> pendingExpensesFrom(
  List<OutboxEntry> entries, {
  required String familyId,
}) {
  final path = expensesCreatePath(familyId);
  final expenses = <api.Expense>[];

  for (final entry in entries) {
    if (entry.method != 'POST' || entry.path != path || entry.body == null) {
      continue;
    }
    final Map<String, dynamic> body;
    try {
      body = jsonDecode(entry.body!) as Map<String, dynamic>;
    } on FormatException {
      continue; // Corps illisible : mieux vaut ne rien montrer qu'une ligne vide.
    }

    final amount = body['amountCents'];
    expenses.add(
      api.Expense(
        id: PendingLocal.id(entry.id),
        title: body['title']?.toString() ?? '',
        category: body['category']?.toString() ?? 'autre',
        amountCents: amount is int ? amount : 0,
        expenseDate: body['expenseDate']?.toString() ?? '',
        payerMembershipId: body['payerMembershipId']?.toString(),
        childIds: (body['childIds'] as List<dynamic>? ?? const [])
            .map((c) => c.toString())
            .toList(),
      ),
    );
  }

  return expenses;
}
