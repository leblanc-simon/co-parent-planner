import 'package:drift/drift.dart';

import '../../../sync/outbox_status.dart';
import '../database.dart';
import '../tables/outbox_entry.dart';

part 'outbox_dao.g.dart';

/// Accès aux entrées outbox (Lot 8 §3.1).
///
/// Toutes les lectures sont **filtrées par compte** : sur un appareil partagé, les
/// mutations en file de A ne doivent jamais être rejouées, comptées ni affichées
/// sous l'identité de B (revue mobile 2026-08, constats C2/C9).
@DriftAccessor(tables: [OutboxEntries])
class OutboxDao extends DatabaseAccessor<AppDatabase> with _$OutboxDaoMixin {
  OutboxDao(super.db);

  Future<void> enqueue(OutboxEntriesCompanion entry) =>
      into(outboxEntries).insert(entry);

  /// Entrées à (re)jouer pour [userId], FIFO strict par `createdAt` : `pending`
  /// uniquement.
  Future<List<OutboxEntry>> pendingEntries(String userId) {
    return (select(outboxEntries)
          ..where(
            (t) =>
                t.userId.equals(userId) &
                t.status.equalsValue(OutboxStatus.pending),
          )
          ..orderBy([(t) => OrderingTerm.asc(t.createdAt)]))
        .get();
  }

  /// Entrées laissées `inFlight` par une interruption (app tuée pendant l'envoi).
  ///
  /// Sans cette reprise, `pendingEntries` ne les sélectionnait plus jamais et la
  /// mutation était perdue en silence (constat C7). Le rejeu est sûr : l'entrée
  /// porte une `Idempotency-Key` stable, que le serveur dédoublonne (§6.3).
  Future<int> requeueStuckInFlight(String userId) {
    return (update(outboxEntries)..where(
          (t) =>
              t.userId.equals(userId) &
              t.status.equalsValue(OutboxStatus.inFlight),
        ))
        .write(
          const OutboxEntriesCompanion(status: Value(OutboxStatus.pending)),
        );
  }

  /// Toutes les entrées non finalisées de [userId] (pour l'UI de synchro), les
  /// plus récentes d'abord.
  Future<List<OutboxEntry>> activeEntries(String userId) {
    return (select(outboxEntries)
          ..where(
            (t) =>
                t.userId.equals(userId) &
                t.status.equalsValue(OutboxStatus.synced).not(),
          )
          ..orderBy([(t) => OrderingTerm.desc(t.createdAt)]))
        .get();
  }

  /// Entrées refusées par le serveur, en attente d'une décision de l'utilisateur
  /// (relancer ou abandonner, US-OFF-5).
  Future<List<OutboxEntry>> rejectedEntries(String userId) {
    return (select(outboxEntries)
          ..where(
            (t) =>
                t.userId.equals(userId) &
                t.status.equalsValue(OutboxStatus.rejected),
          )
          ..orderBy([(t) => OrderingTerm.desc(t.createdAt)]))
        .get();
  }

  /// Flux réactif des entrées refusées (bandeau + écran de synchro).
  Stream<List<OutboxEntry>> watchRejected(String userId) {
    return (select(outboxEntries)
          ..where(
            (t) =>
                t.userId.equals(userId) &
                t.status.equalsValue(OutboxStatus.rejected),
          )
          ..orderBy([(t) => OrderingTerm.desc(t.createdAt)]))
        .watch();
  }

  Future<OutboxEntry?> byId(String id) =>
      (select(outboxEntries)..where((t) => t.id.equals(id))).getSingleOrNull();

  Future<void> deleteEntry(String id) =>
      (delete(outboxEntries)..where((t) => t.id.equals(id))).go();

  /// Purge les entrées confirmées (ne pas laisser grossir la base locale,
  /// US-OFF-5). Appelée après chaque `flush()` — avant la revue mobile 2026-08,
  /// cette méthode n'avait aucun appelant de production (constats C16/C65).
  Future<int> purgeSynced(String userId) =>
      (delete(outboxEntries)..where(
            (t) =>
                t.userId.equals(userId) &
                t.status.equalsValue(OutboxStatus.synced),
          ))
          .go();

  /// Purge à la déconnexion volontaire et à la suppression de compte.
  Future<int> purgeUser(String userId) =>
      (delete(outboxEntries)..where((t) => t.userId.equals(userId))).go();

  Future<int> purgeAll() => delete(outboxEntries).go();

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

  Future<int> countByStatus(String userId, OutboxStatus status) async {
    final count = countAll();
    final query = selectOnly(outboxEntries)
      ..addColumns([count])
      ..where(
        outboxEntries.userId.equals(userId) &
            outboxEntries.status.equalsValue(status),
      );
    final row = await query.getSingle();
    return row.read(count) ?? 0;
  }

  /// Flux réactif du nombre d'entrées d'un statut (badges/compteur de synchro, US-OFF-5).
  Stream<int> watchCountByStatus(String userId, OutboxStatus status) {
    final count = countAll();
    final query = selectOnly(outboxEntries)
      ..addColumns([count])
      ..where(
        outboxEntries.userId.equals(userId) &
            outboxEntries.status.equalsValue(status),
      );
    return query.map((row) => row.read(count) ?? 0).watchSingle();
  }
}
