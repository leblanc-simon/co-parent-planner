import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../l10n/app_localizations.dart';
import '../../agenda/data/agenda_repository.dart';
import '../../agenda/presentation/agenda_screen.dart';
import '../../auth/application/auth_controller.dart';
import '../../auth/application/auth_state.dart';
import '../../custody/presentation/custody_screen.dart';
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
              _EmptyCard(
                key: const ValueKey('home-custody-card'),
                title: l.homeCustodyTitle,
                message: l.custodyOpen,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => const CustodyScreen(),
                  ),
                ),
              )
            else if (family != null && family.isIntact)
              _EmptyCard(
                title: l.homeCustodyTitle,
                message: l.homeFamilyIntact,
                positive: true,
              ),
            const SizedBox(height: 16),
            const _UpcomingCard(),
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
    this.onTap,
    super.key,
  });

  final String title;
  final String message;
  final bool positive;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final content = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(title.toUpperCase(), style: AppTypography.eyebrow),
            ),
            if (onTap != null)
              const Icon(Icons.chevron_right, color: AppColors.muted, size: 20),
          ],
        ),
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
    );

    if (onTap == null) {
      return AppCard(child: content);
    }
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadii.card),
      child: AppCard(child: content),
    );
  }
}

/// Bloc « À venir » (US-AG-7) : les 4 prochaines occurrences de la famille active.
/// Masqué (fallback état vide) si l'agenda n'est pas accessible ou vide.
class _UpcomingCard extends ConsumerWidget {
  const _UpcomingCard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final upcoming = ref
        .watch(familyEventsProvider)
        .maybeWhen(
          data: (events) {
            final now = DateTime.now();
            return events
                .where((e) => DateTime.parse(e.startsAt).isAfter(now))
                .take(4)
                .toList();
          },
          orElse: () => const <api.Event>[],
        );

    if (upcoming.isEmpty) {
      return _EmptyCard(
        title: l.homeUpcomingTitle,
        message: l.homeUpcomingEmpty,
        onTap: () => _open(context),
      );
    }

    return InkWell(
      key: const ValueKey('home-upcoming-card'),
      onTap: () => _open(context),
      borderRadius: BorderRadius.circular(AppRadii.card),
      child: AppCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    l.homeUpcomingTitle.toUpperCase(),
                    style: AppTypography.eyebrow,
                  ),
                ),
                const Icon(
                  Icons.chevron_right,
                  color: AppColors.muted,
                  size: 20,
                ),
              ],
            ),
            const SizedBox(height: 10),
            for (final e in upcoming)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Row(
                  children: [
                    CategoryPill(category: e.category),
                    const SizedBox(width: 8),
                    Text(
                      _when(e),
                      style: const TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w600,
                        color: AppColors.muted,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(child: Text(e.title)),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }

  /// « jj/MM » (all-day) ou « jj/MM HH:mm » — startsAt est déjà dans le fuseau famille.
  static String _when(api.Event e) {
    final day = '${e.startsAt.substring(8, 10)}/${e.startsAt.substring(5, 7)}';
    return e.allDay ? day : '$day ${e.startsAt.substring(11, 16)}';
  }

  void _open(BuildContext context) => Navigator.of(
    context,
  ).push(MaterialPageRoute<void>(builder: (_) => const AgendaScreen()));
}
