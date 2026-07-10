import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import '../../sync/outbox_status.dart';
import 'daos/cache_dao.dart';
import 'daos/outbox_dao.dart';
import 'tables/cache_entry.dart';
import 'tables/outbox_entry.dart';

part 'database.g.dart';

/// Base locale SQLite (drift). Offline mobile (Lot 8 §2.2/§3.1) : file d'écritures
/// (`OutboxEntries`) + cache de lecture générique cloisonné famille (`CacheEntries`).
@DriftDatabase(
  tables: [OutboxEntries, CacheEntries],
  daos: [OutboxDao, CacheDao],
)
class AppDatabase extends _$AppDatabase {
  AppDatabase() : super(_openConnection());

  /// Constructeur de test : injecter `NativeDatabase.memory()`.
  AppDatabase.forTesting(super.executor);

  @override
  int get schemaVersion => 2;

  @override
  MigrationStrategy get migration => MigrationStrategy(
    onCreate: (m) => m.createAll(),
    onUpgrade: (m, from, to) async {
      // v1 → v2 (Lot 8) : ajout du cache de lecture. Les statuts outbox ont changé
      // de sémantique mais l'app n'est pas publiée (aucune donnée locale à préserver).
      if (from < 2) {
        await m.createTable(cacheEntries);
      }
    },
  );
}

LazyDatabase _openConnection() {
  return LazyDatabase(() async {
    final dir = await getApplicationSupportDirectory();
    final file = File(p.join(dir.path, 'coparentplanner.sqlite'));
    return NativeDatabase.createInBackground(file);
  });
}
