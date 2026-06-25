import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'features/auth/application/auth_controller.dart';
import 'features/family/application/family_context.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  final container = ProviderContainer();
  // Restaure la famille active puis la session (token -> /api/me) avant l'UI,
  // pour que le routeur affiche d'emblee le bon ecran.
  await container.read(familyContextProvider.notifier).hydrate();
  await container.read(authControllerProvider.notifier).restoreSession();

  runApp(
    UncontrolledProviderScope(
      container: container,
      child: const CoParentPlannerApp(),
    ),
  );
}
