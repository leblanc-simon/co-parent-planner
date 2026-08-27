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
  int get schemaVersion => 3;

  @override
  MigrationStrategy get migration => MigrationStrategy(
    onCreate: (m) => m.createAll(),
    onUpgrade: (m, from, to) async {
      // v1 → v2 (Lot 8) : ajout du cache de lecture. Les statuts outbox ont changé
      // de sémantique mais l'app n'est pas publiée (aucune donnée locale à préserver).
      if (from < 2) {
        await m.createTable(cacheEntries);
      }
      // v2 → v3 (revue mobile 2026-08, constats C2/C9 et C3/C8) : cloisonnement du
      // stockage local **par compte**. Les entrées antérieures n'ont pas de
      // propriétaire connu : les rejouer ou les servir sous l'identité du compte
      // suivant serait précisément le défaut corrigé — on les supprime donc au lieu
      // de les migrer avec un `userId` vide.
      if (from < 3) {
        // Le cache de lecture est de la donnée dérivée : on le recrée à vide
        // plutôt que d'inventer un propriétaire aux entrées existantes.
        await m.deleteTable(cacheEntries.actualTableName);
        await m.createTable(cacheEntries);
        // L'outbox garde sa structure (l'historique d'erreurs a de la valeur pour
        // l'écran de synchro) mais perd ses entrées sans propriétaire : les
        // rejouer sous l'identité du compte suivant est le défaut corrigé.
        await m.addColumn(outboxEntries, outboxEntries.userId);
        await delete(outboxEntries).go();
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
