import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/deeplink/deep_link_handler.dart';
import 'core/i18n/locale_controller.dart';
import 'core/push/push_controller.dart';
import 'core/theme/app_theme.dart';
import 'features/notifications/application/push_message_handler.dart';
import 'l10n/app_localizations.dart';
import 'routing/app_router.dart';

class CoParentPlannerApp extends ConsumerStatefulWidget {
  const CoParentPlannerApp({super.key});

  @override
  ConsumerState<CoParentPlannerApp> createState() => _CoParentPlannerAppState();
}

class _CoParentPlannerAppState extends ConsumerState<CoParentPlannerApp> {
  DeepLinkHandler? _deepLinks;
  AppLifecycleListener? _lifecycle;

  @override
  void initState() {
    super.initState();
    _deepLinks = DeepLinkHandler(ref.read(goRouterProvider));
    unawaited(_deepLinks!.start());

    // Push (Lot 11) : le contrôleur suit la session dès maintenant (il
    // enregistre l'appareil à la connexion), le gestionnaire capte les taps —
    // y compris celui qui vient de lancer l'app.
    ref.read(pushControllerProvider);
    unawaited(ref.read(pushMessageHandlerProvider).start());
    // Permission retirée dans les réglages système pendant que l'app était
    // en arrière-plan : constatée au retour (US-PUSH-6).
    _lifecycle = AppLifecycleListener(
      onResume: () =>
          unawaited(ref.read(pushControllerProvider.notifier).refresh()),
    );
  }

  @override
  void dispose() {
    _lifecycle?.dispose();
    _deepLinks?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final router = ref.watch(goRouterProvider);
    final locale = ref.watch(localeProvider);

    return MaterialApp.router(
      title: 'CoParentPlanner',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      routerConfig: router,
      scaffoldMessengerKey: pushMessengerKey,
      locale: locale,
      supportedLocales: AppL10n.supportedLocales,
      localizationsDelegates: AppL10n.localizationsDelegates,
    );
  }
}
