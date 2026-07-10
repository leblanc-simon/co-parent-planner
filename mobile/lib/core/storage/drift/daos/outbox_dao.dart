import 'package:drift/drift.dart';

import '../../../sync/outbox_status.dart';
import '../database.dart';
import '../tables/outbox_entry.dart';

part 'outbox_dao.g.dart';

/// Accès aux entrées outbox (Lot 8 §3.1). Le rejeu lit les entrées `pending` dans
/// l'ordre de création (FIFO). Les entrées `synced` sont purgées après succès.
@DriftAccessor(tables: [OutboxEntries])
class OutboxDao extends DatabaseAccessor<AppDatabase> with _$OutboxDaoMixin {
  OutboxDao(super.db);

  Future<void> enqueue(OutboxEntriesCompanion entry) =>
      into(outboxEntries).insert(entry);

  /// Entrées à (re)jouer, FIFO strict par `createdAt` : `pending` uniquement.
  Future<List<OutboxEntry>> pendingEntries() {
    return (select(outboxEntries)
          ..where((t) => t.status.equalsValue(OutboxStatus.pending))
          ..orderBy([(t) => OrderingTerm.asc(t.createdAt)]))
        .get();
  }

  /// Toutes les entrées non finalisées (pour l'UI de synchro), les plus récentes d'abord.
  Future<List<OutboxEntry>> activeEntries() {
    return (select(outboxEntries)
          ..where((t) => t.status.equalsValue(OutboxStatus.synced).not())
          ..orderBy([(t) => OrderingTerm.desc(t.createdAt)]))
        .get();
  }

  Future<void> deleteEntry(String id) =>
      (delete(outboxEntries)..where((t) => t.id.equals(id))).go();

  /// Purge les entrées confirmées (ne pas laisser grossir la base locale, US-OFF-5).
  Future<int> purgeSynced() => (delete(
    outboxEntries,
  )..where((t) => t.status.equalsValue(OutboxStatus.synced))).go();

  Future<void> updateStatus(
    String id,
    OutboxStatus status, {
    String? lastError,
    bool incrementAttempts = false,
  }) async {
    final current = await (select(
      outboxEntries,
    )..where((t) => t.id.equals(id))).getSingleOrNull();
    if (current == null) return;
    await (update(outboxEntries)..where((t) => t.id.equals(id))).write(
      OutboxEntriesCompanion(
        status: Value(status),
        lastError: Value(lastError),
        attempts: incrementAttempts
            ? Value(current.attempts + 1)
            : const Value.absent(),
      ),
    );
  }

  Future<int> countByStatus(OutboxStatus status) async {
    final count = countAll();
    final query = selectOnly(outboxEntries)
      ..addColumns([count])
      ..where(outboxEntries.status.equalsValue(status));
    final row = await query.getSingle();
    return row.read(count) ?? 0;
  }

  /// Flux réactif du nombre d'entrées d'un statut (badges/compteur de synchro, US-OFF-5).
  Stream<int> watchCountByStatus(OutboxStatus status) {
    final count = countAll();
    final query = selectOnly(outboxEntries)
      ..addColumns([count])
      ..where(outboxEntries.status.equalsValue(status));
    return query.map((row) => row.read(count) ?? 0).watchSingle();
  }
}
