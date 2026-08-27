import 'dart:async';

/// Marquage d'une mutation par une `Idempotency-Key` **stable**, y compris quand
/// l'appel passe par un client généré (retrofit) qui n'expose ni en-tête ni
/// `extra` par appel.
///
/// Pourquoi : une création tentée en ligne partait **sans** clé. Si la requête
/// expirait *après* traitement serveur (`receiveTimeout`, pas de réponse HTTP), le
/// dépôt basculait en mode hors-ligne et enfilait une entrée outbox portant une
/// clé **neuve** — le rejeu créait donc un second enregistrement (revue mobile
/// 2026-08, constat C13). En posant la même clé sur la tentative en ligne et sur
/// l'entrée outbox, le dédoublonnage serveur (§6.3) rejoue la réponse initiale.
///
/// La valeur voyage par valeur de zone : elle traverse les `await` de l'appel
/// sans transiter par les signatures du client généré.
abstract final class IdempotencyScope {
  static const Object _zoneKey = #cppIdempotencyKey;

  /// Exécute [body] en marquant les mutations qu'il émet avec [key].
  static Future<T> run<T>(String key, Future<T> Function() body) =>
      runZoned(body, zoneValues: {_zoneKey: key});

  /// Clé courante, `null` hors de tout [run].
  static String? get current {
    final value = Zone.current[_zoneKey];
    return value is String && value.isNotEmpty ? value : null;
  }
}
