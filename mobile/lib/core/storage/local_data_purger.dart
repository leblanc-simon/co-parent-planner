import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'drift/daos/cache_dao.dart';
import 'drift/daos/outbox_dao.dart';
import 'storage_providers.dart';

/// Effacement de l'état local rattaché à un compte (outbox + cache de lecture).
///
/// Appelé à la **déconnexion volontaire**, après une **réinitialisation de mot de
/// passe** (le serveur révoque toutes les sessions) et après une **suppression de
/// compte**. Sans cela, la file de mutations et le cache d'un compte survivaient à
/// la déconnexion : sur un appareil partagé, les mutations de A partaient sous
/// l'identité de B et son cache restait lisible (revue mobile 2026-08, constats
/// C2/C9 et C3/C8).
///
/// À l'inverse, une déconnexion **forcée** (session expirée, refresh révoqué) ne
/// purge rien : les données appartiennent toujours au même compte, sont cloisonnées
/// par son identifiant, et seront rejouées à la reconnexion. Les purger ferait
/// perdre du travail hors-ligne légitime.
class LocalDataPurger {
  LocalDataPurger(this._outbox, this._cache);

  final OutboxDao _outbox;
  final CacheDao _cache;

  Future<void> purgeUser(String userId) async {
    await _outbox.purgeUser(userId);
    await _cache.purgeUser(userId);
  }

  /// Filet de sécurité : efface tout, quel que soit le propriétaire. Utilisé quand
  /// l'identité du compte n'est plus connue (jeton illisible, migration).
  Future<void> purgeEverything() async {
    await _outbox.purgeAll();
    await _cache.purgeAll();
  }
}

final localDataPurgerProvider = Provider<LocalDataPurger>(
  (ref) => LocalDataPurger(
    ref.watch(outboxDaoProvider),
    ref.watch(cacheDaoProvider),
  ),
);
