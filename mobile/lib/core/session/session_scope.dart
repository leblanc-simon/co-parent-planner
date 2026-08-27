import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/application/auth_controller.dart';
import '../../features/auth/application/auth_state.dart';

/// Identité du compte connecté — `null` si personne ne l'est.
///
/// **Tout provider détenant des données de compte doit le `watch`.** Sans cela,
/// un appareil partagé sert au compte suivant le cache Riverpod du précédent :
/// les préférences de notification de A s'affichaient pour B, puis étaient
/// réécrites sous son identité (revue mobile 2026-08, constat C22).
///
/// Le même identifiant cloisonne le stockage local (outbox et cache de lecture,
/// cf. `CacheDao.keyFor` et `OutboxDao`), pour que rien de A ne soit lu ni
/// rejoué sous l'identité de B.
final currentUserIdProvider = Provider<String?>((ref) {
  final auth = ref.watch(authControllerProvider);
  return auth is AuthAuthenticated ? auth.user.id : null;
});
