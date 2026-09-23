import 'push_gateway.dart';

/// Écran où mène le tap sur une notification (Lot 11 US-PUSH-5).
enum PushTarget { home, custody, agenda, exchanges, expenses }

/// Destination résolue d'une notification : l'écran, la famille à activer
/// d'abord s'il le faut, et la ressource précise quand elle est connue.
class PushDestination {
  const PushDestination(
    this.target, {
    this.switchToFamilyId,
    this.exchangeId,
    this.unavailable = false,
  });

  final PushTarget target;

  /// Famille à rendre active avant d'ouvrir l'écran (`X-Family-Id`), si la
  /// notification vient d'une autre famille que celle affichée.
  final String? switchToFamilyId;

  final String? exchangeId;

  /// Vrai quand la ressource n'est plus atteignable — famille quittée,
  /// module coupé : on retombe sur l'accueil **et on le dit**.
  final bool unavailable;
}

/// Traduit une notification en destination, sans rien exécuter.
///
/// La notification ne porte que des identifiants : c'est l'écran ouvert qui
/// relit l'état depuis l'API, et l'API qui tranche les droits (un droit retiré
/// entre l'envoi et le tap donne un 403/404 affiché par l'écran, jamais une
/// donnée). Ici, seules deux choses se décident : la famille, que l'utilisateur
/// doit encore avoir ([memberFamilyIds]), et l'onglet Dépenses, qui n'existe
/// que si le module est actif ([expensesVisible]).
PushDestination resolvePushDestination(
  PushMessage message, {
  required String? activeFamilyId,
  required Set<String> memberFamilyIds,
  required bool Function(String familyId) expensesVisible,
}) {
  final familyId = message.familyId;
  if (familyId != null && !memberFamilyIds.contains(familyId)) {
    return const PushDestination(PushTarget.home, unavailable: true);
  }
  final switchTo = familyId != null && familyId != activeFamilyId
      ? familyId
      : null;
  final target = switch (message.category) {
    'custody' => PushTarget.custody,
    'agenda' => PushTarget.agenda,
    'exchanges' => PushTarget.exchanges,
    'expenses' => PushTarget.expenses,
    _ => PushTarget.home,
  };
  if (target == PushTarget.expenses &&
      !expensesVisible(familyId ?? activeFamilyId ?? '')) {
    return PushDestination(
      PushTarget.home,
      switchToFamilyId: switchTo,
      unavailable: true,
    );
  }
  return PushDestination(
    target,
    switchToFamilyId: switchTo,
    exchangeId: target == PushTarget.exchanges ? message.exchangeId : null,
  );
}
