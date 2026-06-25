import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/deeplink/deep_link_handler.dart';
import 'core/i18n/locale_controller.dart';
import 'core/theme/app_theme.dart';
import 'l10n/app_localizations.dart';
import 'routing/app_router.dart';

class CoParentPlannerApp extends ConsumerStatefulWidget {
  const CoParentPlannerApp({super.key});

  @override
  ConsumerState<CoParentPlannerApp> createState() => _CoParentPlannerAppState();
}

class _CoParentPlannerAppState extends ConsumerState<CoParentPlannerApp> {
  DeepLinkHandler? _deepLinks;

  @override
  void initState() {
    super.initState();
    _deepLinks = DeepLinkHandler(ref.read(goRouterProvider));
    unawaited(_deepLinks!.start());
  }

  @override
  void dispose() {
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
      locale: locale,
      supportedLocales: AppL10n.supportedLocales,
      localizationsDelegates: AppL10n.localizationsDelegates,
    );
  }
}
