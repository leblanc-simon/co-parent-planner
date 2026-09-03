import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/util/civil_date.dart';
import '../../../core/util/date_labels.dart';
import '../../../core/widgets/large_title_header.dart';
import '../../../l10n/app_localizations.dart';
import '../../../routing/routes.dart';
import '../../agenda/data/agenda_repository.dart';
import '../../agenda/presentation/agenda_screen.dart' show CategoryPill;
import '../../auth/application/auth_controller.dart';
import '../../custody/application/current_custody.dart';
import '../../auth/application/auth_state.dart';
import '../../exchanges/data/exchanges_repository.dart';
import '../../exchanges/presentation/exchanges_screen.dart';
import '../../family/application/family_context.dart';
import '../../family/presentation/family_switcher.dart';
import 'settings_tab.dart';

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
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
          children: [
            // U1-01/02/03 : eyebrow date + greeting, pilule famille + engrenage.
            LargeTitleHeader(
              eyebrow: DateLabels.dayOfMonth(l.localeName, DateTime.now()),
              title: '${l.homeGreeting}, $name',
              padding: const EdgeInsets.fromLTRB(0, 12, 0, 0),
              trailing: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (family != null) ...[
                    _FamilyPill(
                      name: family.familyName,
                      onTap: () => showFamilySwitcher(context, ref),
                    ),
                    const SizedBox(width: 8),
                  ],
                  _SettingsButton(
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => const SettingsTab(),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            if (family != null && !family.isIntact)
              _CustodyHeroCard(
                key: const ValueKey('home-custody-card'),
                title: l.homeCustodyTitle,
                onTap: () => context.go(Routes.custody),
              )
            else if (family != null && family.isIntact)
              _EmptyCard(
                title: l.homeCustodyTitle,
                message: l.homeFamilyIntact,
                positive: true,
              ),
            const SizedBox(height: 16),
            const _UpcomingSection(),
            const SizedBox(height: 16),
            const _ExchangesCard(),
            const SizedBox(height: 16),
            _EmptyCard(title: l.homeNotesTitle, message: l.homeNotesEmpty),
          ],
        ),
      ),
    );
  }
}

/// Pilule blanche du sélecteur de famille (U1-02) : nom + double chevron.
class _FamilyPill extends StatelessWidget {
  const _FamilyPill({required this.name, required this.onTap});

  final String name;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      key: const ValueKey('home-family-pill'),
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadii.pill),
      child: Container(
        padding: const EdgeInsets.fromLTRB(12, 5, 10, 5),
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(AppRadii.pill),
          border: Border.all(color: AppColors.border2),
          boxShadow: AppShadows.pill,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 120),
              child: Text(
                name,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontFamily: AppFonts.body,
                  fontSize: 13.5,
                  fontWeight: FontWeight.w600,
                  color: AppColors.ink,
                ),
              ),
            ),
            const SizedBox(width: 4),
            const Icon(Icons.unfold_more, size: 16, color: AppColors.muted2),
          ],
        ),
      ),
    );
  }
}

/// Bouton rond « engrenage » d'accès aux Paramètres (U1-03).
class _SettingsButton extends StatelessWidget {
  const _SettingsButton({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return InkWell(
      key: const ValueKey('home-settings-button'),
      onTap: onTap,
      customBorder: const CircleBorder(),
      child: Tooltip(
        message: l.settingsTitle,
        child: Container(
          width: 38,
          height: 38,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AppColors.card,
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.border2),
            boxShadow: AppShadows.pill,
          ),
          child: const Icon(
            Icons.settings_outlined,
            size: 19,
            color: AppColors.ink2,
          ),
        ),
      ),
    );
  }
}

/// Carte héro « garde de la semaine » (U1-07/08) : liseré gauche 5px **de la
/// couleur de garde du jour**, rayon `cardLg`.
///
/// Le liseré était figé sur `accent`, la couleur du parent A, et le corps répétait
/// « Ouvrir le planning » : la carte ne disait ni chez qui sont les enfants, ni
/// quand ils changent de foyer (constat C59). Tant que la garde du jour n'est pas
/// connue — chargement, hors-ligne, jour hors de l'horizon généré — la carte reste
/// **neutre** plutôt que d'arborer la couleur d'un parent au hasard.
class _CustodyHeroCard extends ConsumerWidget {
  const _CustodyHeroCard({required this.title, required this.onTap, super.key});

  final String title;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final custody = ref.watch(currentCustodyProvider).valueOrNull;
    final slot = custody?.slot;
    final parent = switch (custody?.parentName) {
      final String name when name.isNotEmpty => name,
      _ => slot == 'B' ? l.custodyParentB : l.custodyParentA,
    };

    final stripe = switch (slot) {
      'A' => AppColors.accent,
      'B' => AppColors.positive,
      _ => AppColors.border,
    };
    final message = switch (slot) {
      null => l.custodyOpen,
      _ when custody!.isSelf => l.homeCustodyWithYou,
      _ => l.homeCustodyWithParent(parent),
    };

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadii.cardLg),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadii.cardLg),
        child: Container(
          decoration: BoxDecoration(
            color: AppColors.card,
            borderRadius: BorderRadius.circular(AppRadii.cardLg),
            border: Border.all(color: AppColors.border),
            boxShadow: AppShadows.soft,
          ),
          child: IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Container(
                  key: const ValueKey('home-custody-stripe'),
                  width: 5,
                  color: stripe,
                ),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                title.toUpperCase(),
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
                        Text(
                          message,
                          // Phrase d'état en Bricolage 21 w600 (maquette), et non
                          // plus une ligne de corps grise.
                          style: slot == null
                              ? const TextStyle(
                                  fontFamily: AppFonts.body,
                                  fontSize: 14,
                                  color: AppColors.muted,
                                )
                              : AppTypography.textTheme.titleLarge,
                        ),
                        if (custody != null && custody.hasHandover) ...[
                          const SizedBox(height: 6),
                          Text(
                            l.custodyHandover(
                              custody.handoverName!,
                              DateLabels.isoDayOfMonth(
                                l.localeName,
                                custody.handoverDate!,
                              ),
                              custody.handoverTime!,
                            ),
                            style: const TextStyle(
                              fontFamily: AppFonts.body,
                              fontSize: 13,
                              color: AppColors.muted,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
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

/// Carte d'accès aux Échanges (US-EX-7) : intégration la moins invasive — une carte
/// sur l'accueil plutôt qu'un onglet supplémentaire (le shell garde 4 branches
/// stables). Porte un **badge du nombre d'échanges ouverts** (pastille 999px)
/// reflétant la famille active ; masqué à zéro. Ouvre l'écran Échanges par push.
class _ExchangesCard extends ConsumerWidget {
  const _ExchangesCard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final open = ref
        .watch(familyExchangesProvider)
        .maybeWhen(data: openCount, orElse: () => 0);

    return InkWell(
      key: const ValueKey('home-exchanges-card'),
      onTap: () => Navigator.of(
        context,
      ).push(MaterialPageRoute<void>(builder: (_) => const ExchangesScreen())),
      borderRadius: BorderRadius.circular(AppRadii.card),
      child: AppCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    l.exchangesTitle.toUpperCase(),
                    style: AppTypography.eyebrow,
                  ),
                ),
                if (open > 0) ...[
                  Container(
                    key: const ValueKey('home-exchanges-badge'),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.accent,
                      borderRadius: BorderRadius.circular(AppRadii.pill),
                    ),
                    child: Text(
                      open > 9 ? '9+' : '$open',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                ],
                const Icon(
                  Icons.chevron_right,
                  color: AppColors.muted,
                  size: 20,
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              l.exchangesOpenCount(open),
              style: const TextStyle(
                fontFamily: AppFonts.body,
                fontSize: 14,
                color: AppColors.muted,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Section « À venir » (US-AG-7) : les 4 prochaines occurrences de la famille active,
/// en cartes individuelles (U1-04) avec bloc date à droite. En-tête avec lien
/// « Tout l'agenda » (U1-05). Fallback état vide si l'agenda est inaccessible/vide.
class _UpcomingSection extends ConsumerWidget {
  const _UpcomingSection();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    // Fenêtre dédiée (aujourd'hui → +60 jours), et non la liste **mensuelle** de
    // l'agenda : le 28 du mois, l'accueil annonçait « aucun événement à venir »
    // alors que le suivant tombait la semaine d'après (constats C38/C45).
    final upcoming = ref
        .watch(upcomingEventsProvider)
        .maybeWhen(data: (e) => e, orElse: () => const <api.Event>[]);

    if (upcoming.isEmpty) {
      return _EmptyCard(
        title: l.homeUpcomingTitle,
        message: l.homeUpcomingEmpty,
        onTap: () => context.go(Routes.agenda),
      );
    }

    return Column(
      key: const ValueKey('home-upcoming-card'),
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 2, right: 2, bottom: 10),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  l.homeUpcomingTitle.toUpperCase(),
                  style: AppTypography.eyebrow,
                ),
              ),
              InkWell(
                onTap: () => context.go(Routes.agenda),
                child: Text(
                  l.homeAllAgenda,
                  style: const TextStyle(
                    fontFamily: AppFonts.body,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppColors.accent,
                  ),
                ),
              ),
            ],
          ),
        ),
        for (final e in upcoming)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: _UpcomingTile(event: e),
          ),
      ],
    );
  }
}

/// Carte individuelle d'un événement « à venir » (U1-04/06).
class _UpcomingTile extends StatelessWidget {
  const _UpcomingTile({required this.event});

  final api.Event event;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    // Date **civile du fuseau famille**, dérivée de la chaîne : `DateTime.parse`
    // d'un horodatage à offset renvoie un instant UTC, et en tirer `.day` /
    // `.weekday` affichait la veille — « MAR 30 » à côté de « 00:30 » pour un
    // événement du mercredi 1er, et pour **tout** événement journée entière
    // (constats C20/C36).
    final start = CivilDate.of(event.startsAt) ?? DateTime.now();
    final meta = event.allDay
        ? l.agendaAllDayLabel
        : CivilDate.hm(event.startsAt);

    return AppCard(
      radius: 16,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            // Bloc date empilé : jour abrégé + numéro Bricolage.
            SizedBox(
              width: 46,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    DateLabels.shortWeekday(l.localeName, start).toUpperCase(),
                    style: const TextStyle(
                      fontFamily: AppFonts.body,
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: AppColors.muted2,
                    ),
                  ),
                  Text(
                    '${start.day}',
                    style: const TextStyle(
                      fontFamily: AppFonts.display,
                      fontSize: 20,
                      fontWeight: FontWeight.w700,
                      height: 1,
                      color: AppColors.ink,
                    ),
                  ),
                ],
              ),
            ),
            Container(
              width: 1,
              margin: const EdgeInsets.symmetric(horizontal: 14),
              color: AppColors.border,
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    event.title,
                    style: const TextStyle(
                      fontFamily: AppFonts.body,
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: AppColors.ink,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    meta,
                    style: const TextStyle(
                      fontFamily: AppFonts.body,
                      fontSize: 13,
                      color: AppColors.muted,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            CategoryPill(category: event.category),
          ],
        ),
      ),
    );
  }
}
