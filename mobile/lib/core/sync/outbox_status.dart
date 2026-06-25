/// Etats d'une mutation dans l'outbox (§3.10). Stocke en base via `intEnum`
/// (l'ORDRE des valeurs est donc significatif — ne pas reordonner).
enum OutboxStatus {
  /// En attente de synchro (hors-ligne, ou pas encore tentee).
  pending,

  /// Envoi en cours.
  sending,

  /// Confirme par le serveur (2xx). Etat terminal.
  done,

  /// Conflit serveur (409). Etat terminal au Lot 0 (politique detaillee Lot 8).
  conflict,

  /// Echec (5xx/reseau) : sera replanifie (backoff).
  failed,
}
