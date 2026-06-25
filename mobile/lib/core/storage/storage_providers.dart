import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'drift/daos/outbox_dao.dart';
import 'drift/database.dart';
import 'secure_storage.dart';

/// Stockage securise (tokens, famille active). Surcharge en test via override.
final secureStorageProvider = Provider<SecureStorage>(
  (ref) => FlutterSecureStorageAdapter(),
);

/// Base locale drift. Surcharge en test avec `AppDatabase.forTesting(...)`.
final appDatabaseProvider = Provider<AppDatabase>((ref) {
  final db = AppDatabase();
  ref.onDispose(db.close);
  return db;
});

final outboxDaoProvider = Provider<OutboxDao>(
  (ref) => ref.watch(appDatabaseProvider).outboxDao,
);
