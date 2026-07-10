/// États d'une mutation dans l'outbox (Lot 8 §3.1). Stocké en base via `intEnum`
/// (l'ORDRE des valeurs est significatif — ne pas réordonner ; `pending` reste en 0).
enum OutboxStatus {
  /// En attente de synchro (hors-ligne, ou pas encore tentée). Rejoué en FIFO.
  pending,

  /// Envoi en cours (transitoire, le temps de la requête).
  inFlight,

  /// Confirmé par le serveur (2xx). État terminal ; purgé après succès.
  synced,

  /// Rejeté par le serveur de façon terminale (409 conflit, 403 droits, 422
  /// validation, 404/410 disparu). État terminal ; l'utilisateur peut relancer
  /// (repasse en `pending`) ou abandonner.
  rejected,
}
