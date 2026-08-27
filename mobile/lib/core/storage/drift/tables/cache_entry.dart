import 'package:drift/drift.dart';

/// Cache de lecture générique (Lot 8 §2.2, US-OFF-1) : dernière réponse GET réussie
/// par écran, pour consultation hors-ligne (lecture seule).
///
/// **Cloisonné par compte ET par famille** : la clé est préfixée par `userId` puis
/// `familyId`. Le cloisonnement famille seul laissait le cache d'un compte lisible
/// par le compte suivant connecté sur l'appareil (revue mobile 2026-08, constats
/// C3/C8).
class CacheEntries extends Table {
  /// Clé = `userId::familyId::endpoint` (ex. `u-1::f-1::events`).
  TextColumn get key => text()();
  TextColumn get userId => text().withDefault(const Constant(''))();
  TextColumn get familyId => text()();
  TextColumn get payloadJson => text()(); // liste/objet JSON sérialisé
  DateTimeColumn get cachedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {key};
}
