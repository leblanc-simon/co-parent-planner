/// Signalé quand une mutation a été **mise en file** dans l'outbox (hors-ligne),
/// au lieu d'être exécutée en ligne (Lot 8 §2.2, US-OFF-2). L'appelant (UI) l'interprète
/// comme un succès différé (« en attente de synchro »), pas comme une erreur.
class OutboxQueuedException implements Exception {
  const OutboxQueuedException();
}
