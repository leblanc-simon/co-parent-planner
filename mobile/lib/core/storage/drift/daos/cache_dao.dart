import 'package:drift/drift.dart';

import '../database.dart';
import '../tables/cache_entry.dart';

part 'cache_dao.g.dart';

/// Accès au cache de lecture (Lot 8 §2.2). Upsert par clé `"$familyId::$endpoint"`,
/// lecture par clé, purge par famille (ex. à la déconnexion / suppression de compte).
@DriftAccessor(tables: [CacheEntries])
class CacheDao extends DatabaseAccessor<AppDatabase> with _$CacheDaoMixin {
  CacheDao(super.db);

  static String keyFor(String familyId, String endpoint) =>
      '$familyId::$endpoint';

  Future<void> put(String familyId, String endpoint, String payloadJson) {
    return into(cacheEntries).insertOnConflictUpdate(
      CacheEntriesCompanion.insert(
        key: keyFor(familyId, endpoint),
        familyId: familyId,
        payloadJson: payloadJson,
        cachedAt: DateTime.now(),
      ),
    );
  }

  Future<CacheEntry?> get(String familyId, String endpoint) {
    return (select(cacheEntries)
          ..where((t) => t.key.equals(keyFor(familyId, endpoint))))
        .getSingleOrNull();
  }

  Future<int> purgeFamily(String familyId) {
    return (delete(
      cacheEntries,
    )..where((t) => t.familyId.equals(familyId))).go();
  }
}
