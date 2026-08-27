import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../api/generated/export.dart' as api;
import '../../../l10n/app_localizations.dart';
import '../../../routing/routes.dart';
import '../../auth/presentation/widgets/auth_scaffold.dart';
import '../../family/application/family_context.dart';
import '../../family/data/family_repository.dart';

/// US-ONB-1 sc.6 : utilisateur verifie sans famille -> creer, rejoindre, ou
/// **reprendre une famille existante**.
///
/// Ce troisieme chemin manquait : apres une deconnexion, la famille active est
/// effacee, et l'ecran ne proposait que « creer » ou « rejoindre » — tout
/// utilisateur possedant deja une famille se retrouvait piege ici, sans aucun
/// moyen de revenir dans l'application (revue mobile 2026-08, constat C21).
/// Le cas d'une seule famille est resolu automatiquement par
/// `FamilyContextController.resolveActive()` ; cet ecran couvre le cas de
/// plusieurs familles, et le repli si la resolution automatique a echoue.
class OnboardingScreen extends ConsumerWidget {
  const OnboardingScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final families =
        ref.watch(myFamiliesProvider).value ?? const <api.Family>[];

    return AuthScaffold(
      title: l.onboardingTitle,
      subtitle: families.isEmpty
          ? l.onboardingSubtitle
          : l.onboardingSubtitleWithFamilies,
      children: [
        if (families.isNotEmpty) ...[
          Text(
            l.onboardingPickExisting,
            style: Theme.of(context).textTheme.labelLarge,
          ),
          const SizedBox(height: 8),
          for (final family in families)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: OutlinedButton(
                key: ValueKey('onboarding-family-${family.id}'),
                onPressed: () async {
                  await ref
                      .read(familyContextProvider.notifier)
                      .setActive(
                        FamilyContext(
                          familyId: family.id,
                          familyName: family.name,
                          type: family.type,
                        ),
                      );
                },
                child: Text(family.name),
              ),
            ),
          const SizedBox(height: 14),
        ],
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
