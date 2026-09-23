import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'core/push/firebase_push_gateway.dart';
import 'core/push/push_controller.dart';
import 'features/auth/application/auth_controller.dart';
import 'features/family/application/family_context.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Push (Lot 11) : sans configuration Firebase dans ce build, la passerelle
  // est inerte et l'app démarre normalement, push éteint.
  final push = await FirebasePushGateway.initialize();

  final container = ProviderContainer(
    overrides: [pushGatewayProvider.overrideWithValue(push)],
  );

  // La famille active vient du stockage local : quelques millisecondes, on peut
  // l'attendre pour que le routeur affiche d'emblée le bon écran.
  await container.read(familyContextProvider.notifier).hydrate();

  // La restauration de session, elle, appelle `GET /api/me` : l'attendre laissait
  // un écran vide aussi longtemps que le réseau était lent (revue mobile 2026-08,
  // constat C24). L'UI démarre donc immédiatement sur `AuthRestoring` (écran
  // d'attente), et le routeur redirige dès que l'état est connu.
  final restore = container
      .read(authControllerProvider.notifier)
      .restoreSession();

  runApp(
    UncontrolledProviderScope(
      container: container,
      child: const CoParentPlannerApp(),
    ),
  );

  // Une exception ici ne doit pas rester non capturée : `restoreSession` gère
  // déjà ses erreurs métier, ce filet couvre l'imprévu.
  await restore.catchError((Object _) {});
}
