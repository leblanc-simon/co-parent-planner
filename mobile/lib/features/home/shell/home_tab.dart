import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/application/auth_controller.dart';
import '../../auth/application/auth_state.dart';
import '../../family/application/family_context.dart';

/// US-HOME-1 : accueil = perspective de la famille active + encarts en etat vide
/// (le contenu reel arrive aux lots metier 2/3/4/6). L'encart « garde » est
/// masque pour une famille unie (`intact`).
class HomeTab extends ConsumerWidget {
  const HomeTab({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final family = ref.watch(familyContextProvider);
    final auth = ref.watch(authControllerProvider);
    final name = switch (auth) {
      AuthAuthenticated(:final user) =>
        (user.fullName?.isNotEmpty ?? false) ? user.fullName! : user.email,
      _ => '',
    };

    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
          children: [
            Text(
              '${l.homeGreeting}, $name',
              style: AppTypography.textTheme.displaySmall,
            ),
            if (family != null) ...[
              const SizedBox(height: 4),
              Text(family.familyName, style: AppTypography.eyebrow),
            ],
            const SizedBox(height: 24),
            if (family != null && !family.isIntact)
              _EmptyCard(title: l.homeCustodyTitle, message: l.homeCustodyEmpty)
            else if (family != null && family.isIntact)
              _EmptyCard(
                title: l.homeCustodyTitle,
                message: l.homeFamilyIntact,
                positive: true,
              ),
            const SizedBox(height: 16),
            _EmptyCard(
              title: l.homeUpcomingTitle,
              message: l.homeUpcomingEmpty,
            ),
            const SizedBox(height: 16),
            _EmptyCard(
              title: l.homeDecisionsTitle,
              message: l.homeDecisionsEmpty,
            ),
            const SizedBox(height: 16),
            _EmptyCard(title: l.homeNotesTitle, message: l.homeNotesEmpty),
          ],
        ),
      ),
    );
  }
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({
    required this.title,
    required this.message,
    this.positive = false,
  });

  final String title;
  final String message;
  final bool positive;

  @override
  Widget build(BuildContext context) {
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title.toUpperCase(), style: AppTypography.eyebrow),
          const SizedBox(height: 10),
          Text(
            message,
            style: TextStyle(
              fontFamily: AppFonts.body,
              fontSize: 14,
              color: positive ? AppColors.positiveStrong : AppColors.muted,
            ),
          ),
        ],
      ),
    );
  }
}
