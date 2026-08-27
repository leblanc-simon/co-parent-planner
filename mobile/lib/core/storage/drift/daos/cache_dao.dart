import 'package:drift/drift.dart';

import '../database.dart';
import '../tables/cache_entry.dart';

part 'cache_dao.g.dart';

/// Accès au cache de lecture (Lot 8 §2.2).
///
/// Cloisonnement **par compte puis par famille** : la clé est
/// `"$userId::$familyId::$endpoint"`. Le cloisonnement famille seul laissait le
/// cache d'un compte lisible par le compte suivant connecté sur l'appareil
/// (revue mobile 2026-08, constats C3/C8).
@DriftAccessor(tables: [CacheEntries])
class CacheDao extends DatabaseAccessor<AppDatabase> with _$CacheDaoMixin {
  CacheDao(super.db);

  static String keyFor(String userId, String familyId, String endpoint) =>
      '$userId::$familyId::$endpoint';

  Future<void> put(
    String userId,
    String familyId,
    String endpoint,
    String payloadJson,
  ) {
    return into(cacheEntries).insertOnConflictUpdate(
      CacheEntriesCompanion.insert(
        key: keyFor(userId, familyId, endpoint),
        userId: Value(userId),
        familyId: familyId,
        payloadJson: payloadJson,
        cachedAt: DateTime.now(),
      ),
    );
  }

  Future<CacheEntry?> get(String userId, String familyId, String endpoint) {
    return (select(cacheEntries)
          ..where((t) => t.key.equals(keyFor(userId, familyId, endpoint))))
        .getSingleOrNull();
  }

  Future<int> purgeFamily(String userId, String familyId) {
    return (delete(cacheEntries)
          ..where((t) => t.userId.equals(userId) & t.familyId.equals(familyId)))
        .go();
  }

  /// Purge à la déconnexion volontaire et à la suppression de compte (US-SET-4b).
  Future<int> purgeUser(String userId) {
    return (delete(cacheEntries)..where((t) => t.userId.equals(userId))).go();
  }

  Future<int> purgeAll() => delete(cacheEntries).go();
}
