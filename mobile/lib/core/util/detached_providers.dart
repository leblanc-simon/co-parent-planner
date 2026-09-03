import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Conteneur Riverpod **détaché du cycle de vie du widget**, à capturer avant un
/// `await` de mutation.
///
/// `ref.invalidate` d'un `ConsumerState` commence par `_assertNotDisposed()`, qui
/// lève un `StateError` — un `throw` réel, actif en release, et non un `assert`.
/// Un écran quitté pendant l'envoi voyait donc l'invalidation partir en erreur
/// asynchrone non capturée : le cache gardait la version d'avant la mutation, et
/// le fil rouvert n'affichait pas le message pourtant enregistré côté serveur
/// (revue mobile 2026-08, constat C23).
///
/// L'invalidation doit précisément **survivre** au démontage : la mutation a
/// réussi, le cache doit être rafraîchi, sinon l'écran suivant ment. Ce qui
/// s'arrête au démontage, ce sont les gestes d'interface — `setState`, vidage
/// d'un `TextEditingController`, `Navigator.pop` — qu'un `if (!mounted)` garde.
extension DetachedProviders on BuildContext {
  ProviderContainer get providers =>
      ProviderScope.containerOf(this, listen: false);
}
