// Temps réel des échanges (point 9 de la revue).
//
// Le mobile documentait « pas de live SSE mobile (différé, cohérent Lots 3/4) » : défendable pour
// la garde et l'agenda, beaucoup moins ici, où deux personnes s'écrivent — on rouvrait l'écran pour
// savoir si l'autre avait répondu.
//
// L'abonnement vit dans un provider, et non dans un `State` d'écran : c'est ce qui fait qu'il se
// ferme **tout seul** dès que plus personne n'écoute (`ref.onDispose` sur le `CancelToken`), et
// qu'il se rouvre à la bascule de famille — le jeton étant scopé famille, un abonnement survivant à
// la bascule écouterait les topics de l'ancienne.
//
// Le serveur ne pousse qu'un **signal** ; on relit donc l'état canonique, exactement comme après
// une mutation. Rien de ce qui arrive par le réseau n'est affiché tel quel.

import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/realtime/realtime_client.dart';
import '../../family/application/family_context.dart';
import '../data/exchanges_repository.dart';

/// Signaux du topic **liste** : création, clôture, décision tranchée (badge + cartes).
final exchangesListSignalProvider = StreamProvider.autoDispose<void>((ref) {
  final family = ref.watch(familyContextProvider);
  if (family == null) return const Stream<void>.empty();

  return _watch(ref, ['/families/${family.familyId}/exchanges'], () {
    ref.invalidate(familyExchangesProvider);
  });
});

/// Signaux du topic **détail** d'un échange : messages, votes, bascule.
//
/// Invalide aussi la liste : un message reçu change le décompte et le marqueur de non-lu de la
/// carte, qui reste visible derrière le détail.
final exchangeDetailSignalProvider = StreamProvider.autoDispose
    .family<void, String>((ref, exchangeId) {
      final family = ref.watch(familyContextProvider);
      if (family == null) return const Stream<void>.empty();

      return _watch(
        ref,
        ['/families/${family.familyId}/exchanges/$exchangeId'],
        () {
          ref
            ..invalidate(exchangeDetailProvider(exchangeId))
            ..invalidate(familyExchangesProvider);
        },
      );
    });

/// Ouvre l'abonnement, appelle [onSignal] à chaque signal, et se ferme avec le provider.
Stream<void> _watch(Ref ref, List<String> topics, void Function() onSignal) {
  final cancel = CancelToken();
  ref.onDispose(cancel.cancel);

  return ref
      .watch(realtimeClientProvider)
      .watch(topics, cancelToken: cancel)
      .map((_) {
        onSignal();
      });
}
