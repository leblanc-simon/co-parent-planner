import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../l10n/app_localizations.dart';
import '../../../routing/routes.dart';
import '../../auth/presentation/widgets/auth_scaffold.dart';

/// US-ONB-1 sc.6 : utilisateur verifie sans famille -> creer ou rejoindre.
class OnboardingScreen extends ConsumerWidget {
  const OnboardingScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    return AuthScaffold(
      title: l.onboardingTitle,
      subtitle: l.onboardingSubtitle,
      children: [
        FilledButton(
          key: const ValueKey('onboarding-create'),
          onPressed: () => context.push(Routes.onboardingCreate),
          child: Text(l.onboardingCreate),
        ),
        const SizedBox(height: 14),
        OutlinedButton(
          key: const ValueKey('onboarding-join'),
          onPressed: () => context.push(Routes.onboardingJoin),
          child: Text(l.onboardingJoin),
        ),
      ],
    );
  }
}
