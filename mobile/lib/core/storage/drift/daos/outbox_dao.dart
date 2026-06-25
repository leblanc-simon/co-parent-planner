import 'package:drift/drift.dart';

import '../../../sync/outbox_status.dart';
import '../database.dart';
import '../tables/outbox_entry.dart';

part 'outbox_dao.g.dart';

/// Acces aux entrees outbox (§3.10). Le rejeu lit les entrees `pending`/`failed`
/// dans l'ordre de creation (FIFO).
@DriftAccessor(tables: [OutboxEntries])
class OutboxDao extends DatabaseAccessor<AppDatabase> with _$OutboxDaoMixin {
  OutboxDao(super.db);

  Future<void> enqueue(OutboxEntriesCompanion entry) =>
      into(outboxEntries).insert(entry);

  /// Entrees a (re)jouer, FIFO : pending puis failed, par `createdAt`.
  Future<List<OutboxEntry>> pendingEntries() {
    return (select(outboxEntries)
          ..where(
            (t) =>
                t.status.equalsValue(OutboxStatus.pending) |
                t.status.equalsValue(OutboxStatus.failed),
          )
          ..orderBy([(t) => OrderingTerm.asc(t.createdAt)]))
        .get();
  }

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
}
