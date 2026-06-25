import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import '../../sync/outbox_status.dart';
import 'daos/outbox_dao.dart';
import 'tables/outbox_entry.dart';

part 'database.g.dart';

/// Base locale SQLite (drift). Lot 0 : seule la table outbox est posee
/// (squelette offline §3.10) ; les caches de lecture metier viennent au Lot 8.
@DriftDatabase(tables: [OutboxEntries], daos: [OutboxDao])
class AppDatabase extends _$AppDatabase {
  AppDatabase() : super(_openConnection());

  /// Constructeur de test : injecter `NativeDatabase.memory()`.
  AppDatabase.forTesting(super.executor);

  @override
  int get schemaVersion => 1;
}

LazyDatabase _openConnection() {
  return LazyDatabase(() async {
    final dir = await getApplicationSupportDirectory();
    final file = File(p.join(dir.path, 'coparentplanner.sqlite'));
    return NativeDatabase.createInBackground(file);
  });
}
