import 'package:drift/drift.dart';

import '../../../sync/outbox_status.dart';

/// File d'attente des mutations sortantes (§3.10). Squelette Lot 0 : on pose la
/// table, l'`Idempotency-Key` et le statut « en attente de synchro » ; les
/// mutations metier offline sont validees au Lot 8.
///
/// `headers` fige le `X-Family-Id` au moment de la creation (§4.7 : pas de
/// re-scoping silencieux). Le rejeu se fait dans l'ordre `createdAt` (FIFO).
class OutboxEntries extends Table {
  TextColumn get id => text()(); // UUID v4
  /// Compte propriétaire de la mutation. Le rejeu est **filtré** sur cette
  /// colonne : sur un appareil partagé, les mutations de A ne partent jamais
  /// sous l'identité de B (revue mobile 2026-08, constats C2/C9).
  TextColumn get userId => text().withDefault(const Constant(''))();
  TextColumn get method => text()(); // POST / PATCH / DELETE
  TextColumn get path => text()(); // ex. /api/families
  TextColumn get body => text().nullable()(); // JSON serialise
  TextColumn get headers => text().nullable()(); // JSON (dont X-Family-Id fige)
  TextColumn get idempotencyKey => text()(); // UUID v4 (Idempotency-Key)
  DateTimeColumn get createdAt => dateTime()();
  IntColumn get status => intEnum<OutboxStatus>()();
  IntColumn get attempts => integer().withDefault(const Constant(0))();
  TextColumn get lastError => text().nullable()();

  @override
  Set<Column> get primaryKey => {id};
}
