import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/session/session_scope.dart';
import '../../../core/sync/outbox_queued_exception.dart';
import '../../../core/util/civil_date.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../../children/data/children_repository.dart';
import '../../family/application/family_context.dart';
import '../../members/data/members_repository.dart';
import '../../../core/widgets/large_title_header.dart';
import '../data/agenda_repository.dart';
import '../data/overlay_repository.dart';

/// Filtre de catégorie actif de l'agenda (`''` = toutes). `autoDispose` : réinitialisé
/// à la fermeture de l'écran.
final _agendaFilterProvider = StateProvider.autoDispose<String>((ref) => '');

/// Couleur (texte/barre) d'une catégorie d'agenda (§8).
Color agendaCategoryColor(String category) => switch (category) {
  'sport' => AppColors.catSport,
  'musique' => AppColors.catMusic,
  'ecole' => AppColors.catSchool,
  'sante' => AppColors.catHealth,
  _ => AppColors.catOther,
};

/// US-AG-3/9 — écran Agenda mobile : occurrences du mois courant groupées par jour,
/// avec pastilles de catégorie (§8). Création d'événements **ponctuels** via un
/// formulaire ; les séries s'affichent mais leur création/édition par portée reste
/// web au Lot 4 (offline riche consolidé au Lot 8).
///
/// Overlay familles liées (Lot 5, US-AG-4) : un toggle par lien actif superpose, en
/// **lecture seule**, les événements projetés de la famille liée (badge « Famille liée »,
/// couleur #9C6FB0). Aucune donnée enfant/responsable/lieu n'est exposée (§3.6).
class AgendaScreen extends ConsumerWidget {
  const AgendaScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final family = ref.watch(familyContextProvider);
    final eventsAsync = ref.watch(familyEventsProvider);
    final month = ref.watch(agendaMonthProvider);
    final filter = ref.watch(_agendaFilterProvider);
    // Best-effort : un échec de lecture overlay ne doit pas casser l'agenda propre.
    final overlay = ref
        .watch(overlayEventsProvider)
        .maybeWhen(data: (o) => o, orElse: () => const <OverlayEvent>[]);

    return Scaffold(
      // U-07 : bouton d'ajout encre à coins arrondis (r:11).
      floatingActionButton: family == null
          ? null
          : FloatingActionButton.extended(
              key: const ValueKey('agenda-new'),
              backgroundColor: AppColors.ink,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(11),
              ),
              onPressed: () => _openForm(context),
              icon: const Icon(Icons.add),
              label: Text(l.agendaNew),
            ),
      body: SafeArea(
        child: Column(
          children: [
            // U-08 : grand titre iOS inline.
            LargeTitleHeader(title: l.agendaTitle),
            Expanded(
              child: eventsAsync.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (e, _) => Padding(
                  padding: const EdgeInsets.all(20),
                  child: ErrorBanner(
                    e is ApiException ? e.message : e.toString(),
                  ),
                ),
                data: (result) {
                  final events = result.items;
                  final filteredEvents = filter.isEmpty
                      ? events
                      : events.where((e) => e.category == filter).toList();
                  final filteredOverlay = filter.isEmpty
                      ? overlay
                      : overlay.where((o) => o.category == filter).toList();
                  return RefreshIndicator(
                    onRefresh: () async {
                      ref
                        ..invalidate(familyEventsProvider)
                        ..invalidate(activeLinksProvider)
                        ..invalidate(overlayPreferencesProvider)
                        ..invalidate(overlayEventsProvider);
                      await ref.read(familyEventsProvider.future);
                    },
                    child: ListView(
                      // Sans cette physique, `RefreshIndicator` ne se déclenche jamais
                      // sous ClampingScrollPhysics (défaut Android) quand la liste
                      // n'a rien à défiler — un mois vide ou court était donc
                      // impossible à rafraîchir (constat C44).
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
                      children: [
                        const _MonthNavigator(),
                        const _OverlayToggles(),
                        // U-01 : rangée de filtres par catégorie.
                        const _CategoryFilters(),
                        const SizedBox(height: 8),
                        if (!result.available) ...[
                          const SizedBox(height: 40),
                          // État vide **explicite** : hors-ligne sans rien en cache
                          // pour ce mois. Auparavant indistinguable d'un mois
                          // réellement vide (constat C14).
                          Center(child: Text(l.offlineUnavailable)),
                        ] else if (filteredEvents.isEmpty &&
                            filteredOverlay.isEmpty) ...[
                          const SizedBox(height: 40),
                          Center(
                            child: Text(
                              l.agendaEmptyMonth(_frenchMonth(month)),
                            ),
                          ),
                        ] else
                          ..._buildDays(
                            context,
                            filteredEvents,
                            filteredOverlay,
                          ),
                      ],
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Fusionne, par jour civil du fuseau famille, les occurrences propres et les
  /// événements overlay, **dans une liste unique triée** par la même clé que le
  /// serveur : `(jour, journée entière d'abord, heure, titre)`.
  ///
  /// Les deux sources étaient rendues l'une après l'autre, donc toutes les
  /// activités de la famille liée s'empilaient après les activités propres du jour
  /// — « Tennis 18:00 » avant « Judo 09:00 » (constat C39). Le mobile perd le tri
  /// du serveur parce qu'il fait deux requêtes distinctes ; il doit donc retrier.
  static List<Widget> _buildDays(
    BuildContext context,
    List<api.Event> events,
    List<OverlayEvent> overlay,
  ) {
    final byDay = <String, List<_AgendaItem>>{};
    for (final e in events) {
      final key = CivilDate.ymd(e.startsAt);
      if (key.isEmpty) continue;
      (byDay[key] ??= []).add(_AgendaItem.own(e));
    }
    for (final o in overlay) {
      final key = CivilDate.ymd(o.startsAt);
      if (key.isEmpty) continue;
      (byDay[key] ??= []).add(_AgendaItem.linked(o));
    }
    final keys = byDay.keys.toList()..sort();
    for (final items in byDay.values) {
      items.sort(_AgendaItem.compare);
    }
    final l = AppL10n.of(context);

    return [
      for (final key in keys) ...[
        Padding(
          padding: const EdgeInsets.only(top: 12, bottom: 6),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Text(_frenchDay(key), style: AppTypography.textTheme.titleMedium),
              const SizedBox(width: 8),
              // U-10 : sous-libellé (nombre d'événements du jour).
              Text(
                l.agendaDayEvents(byDay[key]!.length),
                style: const TextStyle(fontSize: 13, color: AppColors.muted2),
              ),
            ],
          ),
        ),
        // U-05 : espacement 8px entre tuiles d'un même jour.
        for (final item in byDay[key]!)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: item.own != null
                ? _EventTile(event: item.own!)
                : _LinkedEventTile(event: item.linked!),
          ),
      ],
    ];
  }

  static String _frenchMonth(DateTime month) =>
      '${_monthNames[month.month]} ${month.year}';

  static void _openForm(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _EventForm(),
    );
  }

  static String _frenchDay(String ymd) {
    final d = DateTime.parse(ymd);
    const days = [
      'lundi',
      'mardi',
      'mercredi',
      'jeudi',
      'vendredi',
      'samedi',
      'dimanche',
    ];
    return '${days[d.weekday - 1]} ${d.day} ${_monthNames[d.month]}';
  }
}

/// Noms de mois en français. Locale unique livrée (fr-FR) : la sortie de ces
/// libellés vers l'ARB est traitée à l'étape 8 du plan de revue (constats
/// C40/C61), qui centralise tout le formatage de dates.
const List<String> _monthNames = [
  '',
  'janvier',
  'février',
  'mars',
  'avril',
  'mai',
  'juin',
  'juillet',
  'août',
  'septembre',
  'octobre',
  'novembre',
  'décembre',
];

/// Élément unifié de la liste d'un jour : occurrence propre **ou** événement d'une
/// famille liée. Exister sous un type commun est ce qui permet de trier les deux
/// sources ensemble (constat C39).
class _AgendaItem {
  _AgendaItem.own(api.Event event)
    : own = event,
      linked = null,
      startsAt = event.startsAt,
      allDay = event.allDay,
      title = event.title;

  _AgendaItem.linked(OverlayEvent event)
    : own = null,
      linked = event,
      startsAt = event.startsAt,
      allDay = event.allDay,
      title = event.title;

  final api.Event? own;
  final OverlayEvent? linked;
  final String startsAt;
  final bool allDay;
  final String title;

  /// Même clé de tri que `AgendaReader::occurrences` côté serveur : journée entière
  /// d'abord, puis heure, puis titre.
  static int compare(_AgendaItem a, _AgendaItem b) {
    if (a.allDay != b.allDay) return a.allDay ? -1 : 1;
    final byStart = a.startsAt.compareTo(b.startsAt);
    if (byStart != 0) return byStart;
    return a.title.compareTo(b.title);
  }
}

/// Navigation de mois. L'écran était figé sur le mois courant alors que le
/// formulaire acceptait des dates à trois ans : un événement créé pour un autre
/// mois devenait invisible (constat C37).
class _MonthNavigator extends ConsumerWidget {
  const _MonthNavigator();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final month = ref.watch(agendaMonthProvider);
    final now = DateTime.now();
    final isCurrentMonth = month.year == now.year && month.month == now.month;

    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        children: [
          IconButton(
            key: const ValueKey('agenda-month-prev'),
            icon: const Icon(Icons.chevron_left),
            tooltip: l.agendaMonthPrevious,
            onPressed: () => ref.read(agendaMonthProvider.notifier).shift(-1),
          ),
          Expanded(
            child: Text(
              '${_monthNames[month.month]} ${month.year}',
              key: const ValueKey('agenda-month-label'),
              textAlign: TextAlign.center,
              style: AppTypography.textTheme.titleMedium,
            ),
          ),
          IconButton(
            key: const ValueKey('agenda-month-next'),
            icon: const Icon(Icons.chevron_right),
            tooltip: l.agendaMonthNext,
            onPressed: () => ref.read(agendaMonthProvider.notifier).shift(1),
          ),
          if (!isCurrentMonth)
            TextButton(
              key: const ValueKey('agenda-month-today'),
              onPressed: () => ref.read(agendaMonthProvider.notifier).reset(),
              child: Text(l.agendaMonthToday),
            ),
        ],
      ),
    );
  }
}

/// Section « Overlay familles liées » : un toggle par lien actif, reflétant/écrivant la préférence
/// (vue agenda) du membre. Masquée s'il n'existe aucun lien actif.
class _OverlayToggles extends ConsumerWidget {
  const _OverlayToggles();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final family = ref.watch(familyContextProvider);
    final links = ref
        .watch(activeLinksProvider)
        .maybeWhen(data: (x) => x, orElse: () => const <api.FamilyLink>[]);
    if (family == null || links.isEmpty) {
      return const SizedBox.shrink();
    }
    final prefs = ref
        .watch(overlayPreferencesProvider)
        .maybeWhen(
          data: (p) => p,
          orElse: () => const <api.OverlayPreference>[],
        );
    final enabled = <String, bool>{
      for (final p in prefs)
        if ('agenda' == p.view) p.familyLinkId: p.enabled,
    };

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (final link in links)
            SwitchListTile(
              key: ValueKey('overlay-toggle-${link.id}'),
              contentPadding: EdgeInsets.zero,
              // U-09 : puce carrée 10×10 r:3 (couleur famille liée).
              secondary: Container(
                width: 10,
                height: 10,
                decoration: BoxDecoration(
                  color: AppColors.linked,
                  borderRadius: BorderRadius.circular(3),
                ),
              ),
              title: Text(l.overlayToggle(link.otherFamilyName)),
              value: enabled[link.id] ?? false,
              onChanged: (v) async {
                final messenger = ScaffoldMessenger.of(context);
                try {
                  await ref
                      .read(overlayRepositoryProvider)
                      .setPreference(
                        family.familyId,
                        familyLinkId: link.id,
                        view: 'agenda',
                        enabled: v,
                      );
                  ref
                    ..invalidate(overlayPreferencesProvider)
                    ..invalidate(overlayEventsProvider);
                } on ApiException catch (e) {
                  messenger.showSnackBar(SnackBar(content: Text(e.message)));
                }
              },
            ),
        ],
      ),
    );
  }
}

/// Tuile d'un événement overlay (famille liée) : **lecture seule**, teinte #9C6FB0, badge
/// « Famille liée ». Ne montre que l'heure, le titre et la catégorie (projection §3.6).
class _LinkedEventTile extends StatelessWidget {
  const _LinkedEventTile({required this.event});

  final OverlayEvent event;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final time = event.allDay
        ? l.agendaAllDayLabel
        : (event.startsAt.length >= 16 ? event.startsAt.substring(11, 16) : '');

    return AppCard(
      radius: 16,
      padding: const EdgeInsets.all(14),
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // U-11 : barre latérale famille liée, 4px, rayon 3, étirée.
            Container(
              width: 4,
              decoration: BoxDecoration(
                color: AppColors.linked,
                borderRadius: BorderRadius.circular(3),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          event.title,
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                      ),
                      // U-04 : heure en trailing.
                      Text(
                        time,
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF6F685C),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  // La catégorie fait partie de la projection anti-fuite (§3.6) et
                  // le filtre par catégorie s'applique aux événements liés : ne pas
                  // l'afficher rendait le résultat du filtre invérifiable, et deux
                  // activités liées de catégories différentes identiques à l'œil
                  // (constat C43). Le badge « Famille liée » reste le marqueur
                  // d'origine, comme dans la maquette.
                  Row(
                    children: [
                      CategoryPill(category: event.category),
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.linkedSoft,
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          l.overlayLinkedFamily,
                          style: const TextStyle(
                            color: AppColors.linked,
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EventTile extends ConsumerWidget {
  const _EventTile({required this.event});

  final api.Event event;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final time = event.allDay
        ? l.agendaAllDayLabel
        : event.startsAt.substring(11, 16);
    final catColor = agendaCategoryColor(event.category);

    // U-03 : métadonnées (enfant / responsable) résolues au mieux via les providers.
    final members = ref
        .watch(familyMembersProvider)
        .maybeWhen(data: (m) => m, orElse: () => const <api.Member>[]);
    final children = ref
        .watch(familyChildrenProvider)
        .maybeWhen(data: (c) => c, orElse: () => const <api.Child>[]);

    String? responsibleName;
    for (final m in members) {
      if (m.id == event.responsibleMembershipId) {
        responsibleName = (m.fullName?.isNotEmpty ?? false)
            ? m.fullName
            : m.email;
        break;
      }
    }
    final String childLabel;
    if (event.wholeFamily) {
      childLabel = l.agendaWholeFamily;
    } else {
      final ids = event.childIds ?? const <String>[];
      childLabel = [
        for (final c in children)
          if (ids.contains(c.id)) c.firstName,
      ].join(', ');
    }
    final hasLocation = event.location != null && event.location!.isNotEmpty;

    return InkWell(
      // Aucun chemin d'édition ni de suppression n'existait sur mobile : une faute
      // de frappe ou une mauvaise date ne pouvait se corriger qu'en ouvrant le web
      // (constat C41). `deleteEvent` était déjà écrit, sans aucun appelant.
      key: ValueKey('agenda-event-${event.id}'),
      borderRadius: BorderRadius.circular(16),
      onTap: () => showModalBottomSheet<void>(
        context: context,
        builder: (_) => _EventActions(event: event),
      ),
      child: AppCard(
        radius: 16,
        padding: const EdgeInsets.all(14),
        child: IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // U-02 : barre latérale de catégorie, 4px, rayon 3, étirée.
              Container(
                width: 4,
                decoration: BoxDecoration(
                  color: catColor,
                  borderRadius: BorderRadius.circular(3),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            event.title,
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ),
                        if (event.isRecurring) ...[
                          const Icon(
                            Icons.repeat,
                            size: 15,
                            color: AppColors.muted2,
                          ),
                          const SizedBox(width: 8),
                        ],
                        // U-04 : heure en trailing.
                        Text(
                          time,
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF6F685C),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        CategoryPill(category: event.category),
                        if (childLabel.isNotEmpty) ...[
                          const SizedBox(width: 8),
                          Flexible(
                            child: Text(
                              childLabel,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 13,
                                color: AppColors.muted,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    if (hasLocation ||
                        (responsibleName != null &&
                            responsibleName.isNotEmpty)) ...[
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          Icon(
                            hasLocation
                                ? Icons.place_outlined
                                : Icons.person_outline,
                            size: 13,
                            color: const Color(0xFFB7AF9F),
                          ),
                          const SizedBox(width: 4),
                          Flexible(
                            child: Text(
                              [
                                if (hasLocation) event.location!,
                                if (responsibleName != null &&
                                    responsibleName.isNotEmpty)
                                  responsibleName,
                              ].join(' · '),
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 13,
                                color: AppColors.muted,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Actions sur un événement propre : suppression avec confirmation nommée, alignée
/// sur le garde-fou destructif appliqué côté web.
///
/// Une occurrence de **série** n'est pas supprimable ici : la suppression par portée
/// (US-AG-6) reste web, et le dire explicitement vaut mieux que de ne rien proposer.
class _EventActions extends ConsumerWidget {
  const _EventActions({required this.event});

  final api.Event event;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final isSeries = event.isRecurring || event.seriesId != null;

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              event.title,
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 12),
            if (isSeries)
              Text(
                l.agendaSeriesReadOnly,
                style: const TextStyle(color: AppColors.muted, fontSize: 13),
              )
            else
              ListTile(
                key: const ValueKey('agenda-delete'),
                contentPadding: EdgeInsets.zero,
                leading: const Icon(
                  Icons.delete_outline,
                  color: AppColors.catHealth,
                ),
                title: Text(
                  l.agendaDelete,
                  style: const TextStyle(color: AppColors.catHealth),
                ),
                onTap: () => _confirmDelete(context, ref, l),
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _confirmDelete(
    BuildContext context,
    WidgetRef ref,
    AppL10n l,
  ) async {
    final sheet = Navigator.of(context);
    final messenger = ScaffoldMessenger.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(l.agendaDeleteConfirmTitle),
        content: Text(l.agendaDeleteConfirmBody(event.title)),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(l.actionCancel),
          ),
          TextButton(
            key: const ValueKey('agenda-delete-confirm'),
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(
              l.agendaDelete,
              style: const TextStyle(color: AppColors.catHealth),
            ),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await ref.read(agendaRepositoryProvider).deleteEvent(event.id);
      ref
        ..invalidate(familyEventsProvider)
        ..invalidate(upcomingEventsProvider);
      if (sheet.canPop()) sheet.pop();
      messenger.showSnackBar(SnackBar(content: Text(l.agendaDeleted)));
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    }
  }
}

/// U-01 : rangée de filtres par catégorie (pastilles). « Tout » réinitialise le filtre.
class _CategoryFilters extends ConsumerWidget {
  const _CategoryFilters();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final selected = ref.watch(_agendaFilterProvider);
    final entries = <(String, String, Color)>[
      ('', l.agendaFilterAll, AppColors.ink),
      ('sport', l.catSport, AppColors.catSport),
      ('musique', l.catMusic, AppColors.catMusic),
      ('ecole', l.catSchool, AppColors.catSchool),
      ('sante', l.catHealth, AppColors.catHealth),
      ('autre', l.catOther, AppColors.catOther),
    ];
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final (value, label, color) in entries)
          _FilterChip(
            label: label,
            color: color,
            selected: selected == value,
            onTap: () => ref.read(_agendaFilterProvider.notifier).state = value,
          ),
      ],
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.color,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final Color color;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
        decoration: BoxDecoration(
          color: selected ? color.withValues(alpha: 0.14) : AppColors.card,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: selected ? color : AppColors.border2),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: selected ? color : AppColors.muted,
          ),
        ),
      ),
    );
  }
}

/// Pastille de catégorie d'agenda (§8) : couleur + libellé localisé.
class CategoryPill extends StatelessWidget {
  const CategoryPill({super.key, required this.category});

  final String category;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final (fg, bg, label) = switch (category) {
      'sport' => (AppColors.catSport, AppColors.catSportSoft, l.catSport),
      'musique' => (AppColors.catMusic, AppColors.catMusicSoft, l.catMusic),
      'ecole' => (AppColors.catSchool, AppColors.catSchoolSoft, l.catSchool),
      'sante' => (AppColors.catHealth, AppColors.catHealthSoft, l.catHealth),
      _ => (AppColors.catOther, AppColors.catOtherSoft, l.catOther),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(color: fg, fontSize: 11, fontWeight: FontWeight.w600),
      ),
    );
  }
}

/// Formulaire de création d'un événement ponctuel (US-AG-1).
class _EventForm extends ConsumerStatefulWidget {
  const _EventForm();

  @override
  ConsumerState<_EventForm> createState() => _EventFormState();
}

class _EventFormState extends ConsumerState<_EventForm> {
  final _title = TextEditingController();
  final _location = TextEditingController();
  String _category = 'sport';
  String? _responsibleId;
  bool _wholeFamily = true;
  final _selectedChildren = <String>{};
  bool _allDay = false;
  DateTime _start = DateTime.now().add(const Duration(hours: 1));
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _title.dispose();
    _location.dispose();
    super.dispose();
  }

  Future<void> _pickStart() async {
    final date = await showDatePicker(
      context: context,
      initialDate: _start,
      firstDate: DateTime(_start.year - 1),
      lastDate: DateTime(_start.year + 2),
    );
    if (date == null || !mounted) return;
    if (_allDay) {
      setState(() => _start = DateTime(date.year, date.month, date.day, 9));
      return;
    }
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_start),
    );
    if (!mounted) return;
    setState(
      () => _start = DateTime(
        date.year,
        date.month,
        date.day,
        time?.hour ?? _start.hour,
        time?.minute ?? _start.minute,
      ),
    );
  }

  Future<void> _submit() async {
    final family = ref.read(familyContextProvider);
    if (family == null || _responsibleId == null) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    final createdMsg = AppL10n.of(context).agendaCreated;
    final queuedMsg = AppL10n.of(context).offlineQueued;
    try {
      await ref
          .read(agendaRepositoryProvider)
          .createEvent(
            ref.read(currentUserIdProvider) ?? '',
            family.familyId,
            title: _title.text.trim(),
            category: _category,
            responsibleMembershipId: _responsibleId!,
            wholeFamily: _wholeFamily,
            childIds: _wholeFamily ? const [] : _selectedChildren.toList(),
            allDay: _allDay,
            startsAt: _allDay ? _dateOnly(_start) : _isoWithOffset(_start),
            location: _location.text.trim().isEmpty
                ? null
                : _location.text.trim(),
          );
      ref.invalidate(familyEventsProvider);
      if (navigator.canPop()) navigator.pop();
      messenger.showSnackBar(SnackBar(content: Text(createdMsg)));
    } on OutboxQueuedException {
      // Hors-ligne : la création est en file (US-OFF-2), rejouée au retour réseau.
      if (navigator.canPop()) navigator.pop();
      messenger.showSnackBar(SnackBar(content: Text(queuedMsg)));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final members = ref
        .watch(familyMembersProvider)
        .maybeWhen(
          data: (m) => m.where((x) => x.role != 'child').toList(),
          orElse: () => const <api.Member>[],
        );
    final children = ref
        .watch(familyChildrenProvider)
        .maybeWhen(data: (c) => c, orElse: () => const <api.Child>[]);
    _responsibleId ??= members.isNotEmpty ? members.first.id : null;

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          top: 20,
          bottom: MediaQuery.of(context).viewInsets.bottom + 20,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(l.agendaNew, style: AppTypography.textTheme.titleLarge),
              const SizedBox(height: 12),
              if (_error != null) ...[
                ErrorBanner(_error!),
                const SizedBox(height: 12),
              ],
              TextField(
                key: const ValueKey('agenda-title'),
                controller: _title,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(labelText: l.agendaFieldTitle),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _category,
                decoration: InputDecoration(labelText: l.agendaCategory),
                items: [
                  DropdownMenuItem(value: 'sport', child: Text(l.catSport)),
                  DropdownMenuItem(value: 'musique', child: Text(l.catMusic)),
                  DropdownMenuItem(value: 'ecole', child: Text(l.catSchool)),
                  DropdownMenuItem(value: 'sante', child: Text(l.catHealth)),
                  DropdownMenuItem(value: 'autre', child: Text(l.catOther)),
                ],
                onChanged: (v) => setState(() => _category = v ?? 'sport'),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _responsibleId,
                decoration: InputDecoration(labelText: l.agendaResponsible),
                items: [
                  for (final m in members)
                    DropdownMenuItem(
                      value: m.id,
                      child: Text(
                        (m.fullName?.isNotEmpty ?? false)
                            ? m.fullName!
                            : (m.email ?? m.id),
                      ),
                    ),
                ],
                onChanged: (v) => setState(() => _responsibleId = v),
              ),
              const SizedBox(height: 4),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(l.agendaWholeFamily),
                value: _wholeFamily,
                onChanged: (v) => setState(() => _wholeFamily = v),
              ),
              if (!_wholeFamily)
                Wrap(
                  spacing: 8,
                  children: [
                    for (final c in children)
                      FilterChip(
                        label: Text(c.firstName),
                        selected: _selectedChildren.contains(c.id),
                        onSelected: (on) => setState(() {
                          if (on) {
                            _selectedChildren.add(c.id);
                          } else {
                            _selectedChildren.remove(c.id);
                          }
                        }),
                      ),
                  ],
                ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(l.agendaAllDay),
                value: _allDay,
                onChanged: (v) => setState(() => _allDay = v),
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(l.agendaStart),
                subtitle: Text(
                  _allDay ? _dateOnly(_start) : _humanDateTime(_start),
                ),
                trailing: const Icon(Icons.event),
                onTap: _pickStart,
              ),
              TextField(
                controller: _location,
                decoration: InputDecoration(labelText: l.agendaLocation),
              ),
              const SizedBox(height: 16),
              FilledButton(
                key: const ValueKey('agenda-submit'),
                onPressed:
                    _busy ||
                        _title.text.trim().isEmpty ||
                        _responsibleId == null
                    ? null
                    : _submit,
                child: Text(l.agendaCreate),
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String _dateOnly(DateTime d) =>
      '${_p4(d.year)}-${_p2(d.month)}-${_p2(d.day)}';

  static String _humanDateTime(DateTime d) =>
      '${_p2(d.day)}/${_p2(d.month)} ${_p2(d.hour)}:${_p2(d.minute)}';

  /// ISO 8601 avec l'offset local de l'appareil (l'utilisateur est supposé dans le fuseau famille).
  static String _isoWithOffset(DateTime d) {
    final off = d.timeZoneOffset;
    final sign = off.isNegative ? '-' : '+';
    final oh = _p2(off.inHours.abs());
    final om = _p2(off.inMinutes.abs() % 60);
    return '${_dateOnly(d)}T${_p2(d.hour)}:${_p2(d.minute)}:00$sign$oh:$om';
  }

  static String _p2(int v) => v.toString().padLeft(2, '0');
  static String _p4(int v) => v.toString().padLeft(4, '0');
}
