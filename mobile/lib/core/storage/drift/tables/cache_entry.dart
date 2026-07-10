import 'package:drift/drift.dart';

/// Cache de lecture générique (Lot 8 §2.2, US-OFF-1) : dernière réponse GET réussie
/// par écran, pour consultation hors-ligne (lecture seule). **Cloisonné par famille** :
/// la clé est préfixée par `familyId` → jamais de fuite inter-familles.
class CacheEntries extends Table {
  /// Clé = "$familyId::$endpoint" (ex. "uuid::events").
  TextColumn get key => text()();
  TextColumn get familyId => text()();
  TextColumn get payloadJson => text()(); // liste/objet JSON sérialisé
  DateTimeColumn get cachedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {key};
}
