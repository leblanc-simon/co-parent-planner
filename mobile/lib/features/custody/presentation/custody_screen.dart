import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/error/error_text.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/util/civil_date.dart';
import '../../../core/util/date_labels.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../core/widgets/large_title_header.dart';
import '../../../l10n/app_localizations.dart';
import '../../agenda/data/overlay_repository.dart';
import '../../agenda/presentation/agenda_screen.dart'
    show CategoryPill, agendaCategoryColor;
import '../../family/application/family_context.dart';
import '../application/day_detail.dart';
import '../data/custody_repository.dart';
import 'custody_activities.dart';
import 'custody_settings_screen.dart';
import 'parent_initials.dart';

/// US-CUST-5/6 — écran Garde mobile : calendrier mensuel coloré par parent
/// (terracotta = A, teal = B), jours exceptionnels distingués, statistiques,
/// exceptions et vacances, navigables **mois par mois**.
///
/// Les actions de mutation (réglages du rythme, échange d'un jour, suppression
/// d'une exception, import des vacances) ne sont rendues qu'avec le droit
/// `custody:edit` : les offrir à un membre en lecture seule ne menait qu'à un 403
/// (revue mobile 2026-08, constat C29).
class CustodyScreen extends ConsumerWidget {
  const CustodyScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final family = ref.watch(familyContextProvider);

    if (family == null || family.isIntact) {
      return Scaffold(
        body: SafeArea(
          child: Column(
            children: [
              LargeTitleHeader(title: l.custodyScreenTitle),
              Expanded(child: Center(child: Text(l.custodyIntact))),
            ],
          ),
        ),
      );
    }

    final patternAsync = ref.watch(familyCustodyPatternProvider);
    final canEdit = ref.watch(custodyCanEditProvider);

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            // U-08 : grand titre inline « Planning de garde » + accès au rythme
            // (réservé à `custody:edit` : le formulaire se soldait sinon par un 403).
            LargeTitleHeader(
              title: l.custodyScreenTitle,
              trailing: canEdit
                  ? IconButton(
                      key: const ValueKey('custody-settings-action'),
                      icon: const Icon(Icons.tune),
                      tooltip: l.custodySettingsTitle,
                      onPressed: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => const CustodySettingsScreen(),
                        ),
                      ),
                    )
                  : null,
            ),
            Expanded(
              child: patternAsync.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (e, _) => Padding(
                  padding: const EdgeInsets.all(20),
                  child: ErrorBanner(errorText(context, e)),
                ),
                data: (pattern) => RefreshIndicator(
                  onRefresh: () async {
                    ref.invalidate(familyCustodyPatternProvider);
                    ref.invalidate(familyCustodyDaysProvider);
                    ref.invalidate(familyCustodyStatsProvider);
                    ref.invalidate(familyCustodyExceptionsProvider);
                    ref.invalidate(familyHolidaysProvider);
                    ref.invalidate(custodyMonthActivitiesProvider);
                    await ref.read(familyCustodyDaysProvider.future);
                  },
                  child: pattern == null
                      ? _NoPattern()
                      : _CustodyBody(pattern: pattern),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _NoPattern extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(24),
      children: [
        const SizedBox(height: 40),
        Center(child: Text(l.custodyNoPattern)),
        const SizedBox(height: 16),
        // Configurer un rythme est une mutation : sans `custody:edit`, l'état vide
        // reste informatif mais n'offre plus une action qui finirait en 403 (C29).
        if (ref.watch(custodyCanEditProvider))
          Center(
            child: FilledButton(
              key: const ValueKey('custody-configure'),
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => const CustodySettingsScreen(),
                ),
              ),
              child: Text(l.custodyConfigure),
            ),
          ),
      ],
    );
  }
}

class _CustodyBody extends ConsumerWidget {
  const _CustodyBody({required this.pattern});

  final api.CustodyPattern pattern;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final daysAsync = ref.watch(familyCustodyDaysProvider);
    final statsAsync = ref.watch(familyCustodyStatsProvider);
    final exceptionsAsync = ref.watch(familyCustodyExceptionsProvider);
    final holidaysAsync = ref.watch(familyHolidaysProvider);
    // Overlay familles liées (Lot 5) : pastilles d'activité, best-effort (n'impacte pas la garde).
    final overlayDates = ref
        .watch(overlayGardeDatesProvider)
        .maybeWhen(data: (d) => d, orElse: () => const <String>{});
    // U-09 : activités de la famille active, indexées par jour **couvert** (et non
    // par jour de début : un séjour de trois jours n'apparaissait que sur le premier).
    // Domaine de droit distinct : sans `agenda`, aucune pastille — un membre peut
    // avoir `custody` sans avoir à savoir qu'il y a des activités, ni combien.
    final canSeeAgenda = ref.watch(agendaCanViewProvider);
    final activities = canSeeAgenda
        ? ref
              .watch(custodyMonthActivitiesProvider)
              .maybeWhen(
                data: (a) => a,
                orElse: () => const <String, List<api.Event>>{},
              )
        : const <String, List<api.Event>>{};

    return ListView(
      // Même défaut que l'agenda : sans cette physique le pull-to-refresh est
      // inerte sous Android quand le contenu tient dans l'écran (constat C44).
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        const _OverlayGardeToggles(),
        // U-02 : bandeau résumant le rythme de garde.
        _RuleBanner(pattern: pattern),
        const SizedBox(height: 12),
        daysAsync.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => ErrorBanner(errorText(context, e)),
          data: (days) => _Calendar(
            pattern: pattern,
            days: days,
            overlayDates: overlayDates,
            activities: activities,
          ),
        ),
        const SizedBox(height: 12),
        _Legend(showActivity: canSeeAgenda),
        const SizedBox(height: 20),
        statsAsync.maybeWhen(
          data: (stats) =>
              stats == null ? const SizedBox.shrink() : _Stats(stats: stats),
          orElse: () => const SizedBox.shrink(),
        ),
        const SizedBox(height: 20),
        Text(l.custodyExceptionsTitle, style: AppTypography.eyebrow),
        const SizedBox(height: 8),
        exceptionsAsync.when(
          loading: () => const Center(
            child: Padding(
              padding: EdgeInsets.all(8),
              child: CircularProgressIndicator(),
            ),
          ),
          error: (e, _) => ErrorBanner(errorText(context, e)),
          data: (exceptions) => exceptions.isEmpty
              ? Text(l.custodyExceptionsEmpty)
              : Column(
                  children: [
                    for (final e in exceptions) _ExceptionTile(exception: e),
                  ],
                ),
        ),
        const SizedBox(height: 20),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(l.custodyHolidaysTitle, style: AppTypography.eyebrow),
            if (ref.watch(custodyCanEditProvider)) _ImportHolidaysButton(),
          ],
        ),
        const SizedBox(height: 8),
        holidaysAsync.when(
          loading: () => const SizedBox.shrink(),
          error: (e, _) => ErrorBanner(errorText(context, e)),
          data: (holidays) => holidays.isEmpty
              ? Text(l.custodyHolidaysEmpty)
              : Column(
                  children: [
                    for (final h in holidays)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: _HolidayCard(holiday: h),
                      ),
                  ],
                ),
        ),
      ],
    );
  }
}

class _ImportHolidaysButton extends ConsumerStatefulWidget {
  @override
  ConsumerState<_ImportHolidaysButton> createState() =>
      _ImportHolidaysButtonState();
}

class _ImportHolidaysButtonState extends ConsumerState<_ImportHolidaysButton> {
  bool _busy = false;

  Future<void> _import() async {
    final family = ref.read(familyContextProvider);
    if (family == null) return;
    setState(() => _busy = true);
    final messenger = ScaffoldMessenger.of(context);
    final l = AppL10n.of(context);
    try {
      await ref.read(custodyRepositoryProvider).importHolidays(family.familyId);
      ref.invalidate(familyHolidaysProvider);
      messenger.showSnackBar(
        SnackBar(content: Text(l.custodyHolidaysImported)),
      );
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.localized(l))));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return TextButton.icon(
      key: const ValueKey('custody-import-holidays'),
      onPressed: _busy ? null : _import,
      icon: const Icon(Icons.download, size: 18),
      label: Text(l.custodyImportHolidays),
    );
  }
}

/// Calendrier mensuel : grille lundi→dimanche bâtie à partir des jours renvoyés
/// (déterministe, sans dépendre de l'horloge).
class _Calendar extends ConsumerWidget {
  const _Calendar({
    required this.pattern,
    required this.days,
    this.overlayDates = const {},
    this.activities = const {},
  });

  final api.CustodyPattern pattern;
  final List<api.CustodyDay> days;
  final Set<String> overlayDates;

  /// Activités de la famille, par jour couvert (clé `AAAA-MM-JJ`).
  final Map<String, List<api.Event>> activities;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final month = ref.watch(custodyMonthProvider);
    // La grille est bâtie sur le mois **demandé**, pas sur les jours reçus : sans
    // rythme généré sur ce mois la réponse est vide, et l'écran doit tout de même
    // afficher le mois — sinon naviguer hors de la période générée effaçait le
    // calendrier et ses chevrons, sans retour possible.
    final window = custodyWindow(month);
    final first = window.from;
    final last = window.to;
    final byDate = {for (final d in days) d.date: d};
    // Lundi de la semaine du premier jour → dimanche de la semaine du dernier.
    // Arithmétique via le constructeur DateTime (jours calendaires) : insensible au changement
    // d'heure (le 25/10, +Duration(days:1) = 24 h dupliquerait/sauterait un jour).
    final gridStart = DateTime(
      first.year,
      first.month,
      first.day - (first.weekday - 1),
    );
    final gridEnd = DateTime(
      last.year,
      last.month,
      last.day + (7 - last.weekday),
    );

    final cells = <Widget>[];
    for (final dow in [
      l.weekdayMonday,
      l.weekdayTuesday,
      l.weekdayWednesday,
      l.weekdayThursday,
      l.weekdayFriday,
      l.weekdaySaturday,
      l.weekdaySunday,
    ]) {
      cells.add(_DowHeader(dow.substring(0, 3)));
    }

    final today = DateTime.now();
    final todayKey =
        '${today.year.toString().padLeft(4, '0')}-${today.month.toString().padLeft(2, '0')}-${today.day.toString().padLeft(2, '0')}';

    var cursor = gridStart;
    while (!cursor.isAfter(gridEnd)) {
      final key =
          '${cursor.year.toString().padLeft(4, '0')}-${cursor.month.toString().padLeft(2, '0')}-${cursor.day.toString().padLeft(2, '0')}';
      final day = byDate[key];
      // Appartenance au mois affiché, et non « un jour matérialisé existe » : depuis
      // que la grille suit le mois demandé, un jour du mois hors de la période
      // générée doit rester une case du mois (sans couleur d'attribution) plutôt que
      // de passer pour une case de remplissage.
      final inMonth = cursor.month == month.month && cursor.year == month.year;
      cells.add(
        _DayCell(
          dateKey: key,
          dayNumber: cursor.day,
          inMonth: inMonth,
          day: day,
          slot: _slotFor(day),
          isToday: key == todayKey,
          // Marqueurs conditionnés à l'appartenance au mois, pas à la présence d'un
          // jour matérialisé : une activité tombant sur un jour non planifié doit
          // rester visible.
          hasOverlay: inMonth && overlayDates.contains(key),
          activities: inMonth ? (activities[key] ?? const []) : const [],
        ),
      );
      cursor = DateTime(cursor.year, cursor.month, cursor.day + 1);
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // U-01 : navigation de mois (US-CUST-5). Les deux chevrons étaient des
        // `Container` sans `onTap` : un contrôle mort, que le commentaire justifiait
        // par une limitation serveur inexistante — `custody/days` et `custody/stats`
        // lisent `?from=`/`?to=` (constat C27).
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            // Un appui long sur le titre ramène au mois courant : après plusieurs
            // pas, y revenir demandait autant d'appuis en sens inverse.
            GestureDetector(
              key: const ValueKey('custody-month-reset'),
              onLongPress: () =>
                  ref.read(custodyMonthProvider.notifier).reset(),
              child: Text(
                DateLabels.monthYear(l.localeName, month),
                style: AppTypography.textTheme.titleMedium,
              ),
            ),
            Row(
              children: [
                _MonthNavButton(
                  key: const ValueKey('custody-month-prev'),
                  icon: Icons.chevron_left,
                  tooltip: l.custodyPrevMonth,
                  onTap: () =>
                      ref.read(custodyMonthProvider.notifier).shift(-1),
                ),
                const SizedBox(width: 8),
                _MonthNavButton(
                  key: const ValueKey('custody-month-next'),
                  icon: Icons.chevron_right,
                  tooltip: l.custodyNextMonth,
                  onTap: () => ref.read(custodyMonthProvider.notifier).shift(1),
                ),
              ],
            ),
          ],
        ),
        const SizedBox(height: 8),
        GridView.count(
          crossAxisCount: 7,
          mainAxisSpacing: 4,
          crossAxisSpacing: 4,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          children: cells,
        ),
      ],
    );
  }

  /// 'A' / 'B' / null selon l'appartenance au parent A/B du rythme.
  String? _slotFor(api.CustodyDay? day) {
    final pid = day?.parentMembershipId;
    if (pid == null) return null;
    if (pid == pattern.parentAMembershipId) return 'A';
    if (pid == pattern.parentBMembershipId) return 'B';
    return null;
  }
}

class _DowHeader extends StatelessWidget {
  const _DowHeader(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Text(
        label.toUpperCase(),
        style: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: AppColors.muted2,
        ),
      ),
    );
  }
}

/// Bouton de navigation de mois (U-01) : carré 34×34, rayon 10, chevron atténué.
///
/// La zone tactile est portée à 44 pt (§8.1) autour du carré peint : la cible visible
/// reste celle de la maquette, la cible réelle atteint le minimum.
class _MonthNavButton extends StatelessWidget {
  const _MonthNavButton({
    required this.icon,
    required this.tooltip,
    required this.onTap,
    super.key,
  });

  final IconData icon;
  final String tooltip;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: tooltip,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(22),
        child: SizedBox(
          width: 44,
          height: 44,
          child: Center(
            child: Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: AppColors.card,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: AppColors.border2),
              ),
              child: Icon(icon, size: 20, color: AppColors.muted),
            ),
          ),
        ),
      ),
    );
  }
}

class _DayCell extends ConsumerWidget {
  const _DayCell({
    required this.dateKey,
    required this.dayNumber,
    required this.inMonth,
    required this.day,
    required this.slot,
    this.isToday = false,
    this.hasOverlay = false,
    this.activities = const [],
  });

  final String dateKey;
  final int dayNumber;

  /// Le jour appartient au mois affiché (les cases de complément de semaine, non).
  final bool inMonth;

  final api.CustodyDay? day;
  final String? slot;
  final bool isToday;
  final bool hasOverlay;

  /// Activités de la famille couvrant ce jour (pastilles colorées par catégorie).
  final List<api.Event> activities;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final isException = day?.source == 'exception';
    final isHoliday = day?.source == 'holiday';

    final (bg, borderColor) = switch (slot) {
      'A' => (AppColors.accentSoft2, AppColors.accent),
      'B' => (AppColors.positiveSoft, AppColors.positive),
      _ => (AppColors.surface, AppColors.border),
    };

    // U-04 : numéro centré dans une pastille 25×25 r13 (fond accentué aujourd'hui).
    final numberPill = Container(
      width: 25,
      height: 25,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: isToday && inMonth ? AppColors.ink : Colors.transparent,
        borderRadius: BorderRadius.circular(13),
      ),
      child: Text(
        '$dayNumber',
        style: TextStyle(
          fontSize: 13.5,
          fontWeight: FontWeight.w600,
          color: isToday && inMonth
              ? Colors.white
              : (inMonth ? AppColors.ink : AppColors.muted2),
        ),
      ),
    );

    final cell = Container(
      decoration: BoxDecoration(
        color: inMonth ? bg : Colors.transparent,
        borderRadius: BorderRadius.circular(12),
        // U-06 : l'exception n'a pas de bordure pleine — un liseré pointillé
        // (#857E71) est peint par-dessus ; sinon bordure pleine.
        border: Border.all(
          color: inMonth && !isException ? borderColor : Colors.transparent,
        ),
      ),
      padding: const EdgeInsets.all(4),
      child: Stack(
        children: [
          // U-06 : liseré pointillé pour un jour d'échange exceptionnel (sans icône).
          if (isException)
            Positioned.fill(
              child: CustomPaint(
                painter: _DashedRRectPainter(
                  color: AppColors.muted,
                  radius: 12,
                ),
              ),
            ),
          Center(child: numberPill),
          // Marqueur NON CHROMATIQUE de l'attribution (cdc §8, WCAG 1.4.1) : les deux fonds A/B ne
          // diffèrent que par la teinte, donc l'information disparaissait en niveaux de gris, en
          // daltonisme rouge-vert et en contraste forcé. Bas-gauche : les autres coins sont pris
          // (vacances bas-droite, activités bas-centre, famille liée haut-droite). Miroir du web
          // (`.garde-day__parent`).
          if (inMonth && slot != null)
            Align(
              alignment: Alignment.bottomLeft,
              child: Text(
                ref.watch(parentMarkersProvider).markerForSlot(slot!),
                key: ValueKey('garde-day-marker-$dateKey'),
                style: const TextStyle(
                  fontSize: 9,
                  height: 1,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ink2,
                ),
              ),
            ),
          if (isHoliday)
            const Align(
              alignment: Alignment.bottomRight,
              child: Icon(
                Icons.beach_access,
                size: 11,
                color: AppColors.catSchool,
              ),
            ),
          // U-09 : pastilles d'activité, bas-centre, **colorées par catégorie** (§8)
          // comme les tuiles de l'agenda et les pastilles du planning web — un point
          // gris unique ne disait ni combien d'activités ni de quelle nature.
          // Trois au plus : au-delà, la case de ~45 px ne les distinguerait plus.
          if (activities.isNotEmpty)
            Align(
              alignment: Alignment.bottomCenter,
              child: Padding(
                padding: const EdgeInsets.only(bottom: 1),
                child: Row(
                  key: ValueKey('activity-dots-$dateKey'),
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    for (final e in activities.take(3))
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 0.5),
                        child: _ActivityDot(
                          color: agendaCategoryColor(e.category),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          // U-11 : marqueur famille liée (overlay §3.3) = anneau HAUT-droite.
          // Il était peint bas-droite, par-dessus l'icône de vacances qui y vit déjà
          // (constat C34) : deux marqueurs distincts ne peuvent pas partager un coin.
          if (hasOverlay)
            Align(
              alignment: Alignment.topRight,
              child: _OverlayDot(key: ValueKey('overlay-dot-$dateKey')),
            ),
        ],
      ),
    );

    if (!inMonth) {
      return cell;
    }

    // cdc §8.1 : « le marqueur est aria-hidden — les lecteurs d'écran reçoivent le
    // NOM EN CLAIR, jamais l'initiale seule », sur web comme sur mobile. La case
    // n'était enveloppée d'aucun `Semantics` (il n'y en avait aucun dans toute
    // l'app) : VoiceOver/TalkBack annonçaient « 23, So » (constat C55).
    // `excludeSemantics` joue le rôle de l'`aria-hidden` du gabarit web.
    final markers = ref.watch(parentMarkersProvider);
    final name = slot == null
        ? null
        : (markers.nameForSlot(slot!).isNotEmpty
              ? markers.nameForSlot(slot!)
              : (slot == 'A' ? l.custodyParentA : l.custodyParentB));
    final dayDate = CivilDate.of(dateKey);
    final dateLabel = dayDate == null
        ? dateKey
        : DateLabels.longDate(l.localeName, dayDate);
    final semantics = <String>[
      name == null ? dateLabel : l.custodyDaySemantics(dateLabel, name),
      if (day?.source == 'exception') l.custodyExceptional,
      if (day?.source == 'holiday') l.custodyHoliday,
      if (activities.isNotEmpty) l.custodyActivity,
    ].join(', ');

    return Semantics(
      button: true,
      label: semantics,
      excludeSemantics: true,
      child: InkWell(
        key: ValueKey('garde-day-$dateKey'),
        borderRadius: BorderRadius.circular(12),
        onTap: () => _openDetail(context),
        child: cell,
      ),
    );
  }

  void _openDetail(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => CustodyDaySheet(date: dateKey),
    );
  }
}

class _Legend extends ConsumerWidget {
  const _Legend({this.showActivity = true});

  /// La pastille d'activité n'est expliquée que si elle peut apparaître (droit `agenda`).
  final bool showActivity;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final markers = ref.watch(parentMarkersProvider);
    return Wrap(
      spacing: 16,
      runSpacing: 6,
      children: [
        // Le nom réel du parent dès que les membres sont chargés ; sinon « Parent A »/« Parent B »,
        // que les marqueurs « A »/« B » accompagnent alors naturellement.
        _LegendItem(
          color: AppColors.accent,
          fill: AppColors.accentSoft2,
          marker: markers.markerA,
          label: markers.nameA.isNotEmpty ? markers.nameA : l.custodyParentA,
        ),
        _LegendItem(
          color: AppColors.positive,
          fill: AppColors.positiveSoft,
          marker: markers.markerB,
          label: markers.nameB.isNotEmpty ? markers.nameB : l.custodyParentB,
        ),
        // U-06 : exception = liseré pointillé (pas d'icône).
        _LegendItem(
          color: AppColors.muted,
          label: l.custodyExceptional,
          dashed: true,
        ),
        _LegendItem(
          color: AppColors.catSchool,
          label: l.custodyHoliday,
          icon: Icons.beach_access,
        ),
        // U-09 : pastille d'activité.
        if (showActivity)
          _LegendItem(color: AppColors.ink3, label: l.custodyActivity),
        // U-11 : famille liée = anneau.
        _LegendItem(
          color: AppColors.linked,
          label: l.overlayLinkedFamily,
          ring: true,
        ),
      ],
    );
  }
}

/// U-11 : marqueur famille liée = anneau (bordure 1.5px #9C6FB0, centre transparent).
class _OverlayDot extends StatelessWidget {
  const _OverlayDot({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 6,
      height: 6,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: AppColors.linked, width: 1.5),
      ),
    );
  }
}

/// U-09 : pastille pleine d'activité, à la couleur de sa catégorie (§8).
class _ActivityDot extends StatelessWidget {
  const _ActivityDot({this.color = AppColors.ink3});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 5,
      height: 5,
      decoration: BoxDecoration(color: color, shape: BoxShape.circle),
    );
  }
}

/// Peint un liseré arrondi en pointillés (marqueur d'échange exceptionnel, U-06).
class _DashedRRectPainter extends CustomPainter {
  _DashedRRectPainter({required this.color, this.radius = 12});

  final Color color;
  final double radius;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5;
    final rrect = RRect.fromRectAndRadius(
      Offset.zero & size,
      Radius.circular(radius),
    );
    final path = Path()..addRRect(rrect);
    const dash = 3.0;
    const gap = 2.5;
    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        canvas.drawPath(metric.extractPath(distance, distance + dash), paint);
        distance += dash + gap;
      }
    }
  }

  @override
  bool shouldRepaint(_DashedRRectPainter oldDelegate) =>
      oldDelegate.color != color || oldDelegate.radius != radius;
}

/// Section « Overlay familles liées » (vue garde) : un toggle par lien actif, reflétant/écrivant
/// la préférence (vue garde) du membre. Masquée s'il n'existe aucun lien actif.
class _OverlayGardeToggles extends ConsumerWidget {
  const _OverlayGardeToggles();

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
        if ('garde' == p.view) p.familyLinkId: p.enabled,
    };

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l.overlaySectionTitle,
            style: AppTypography.textTheme.titleMedium,
          ),
          for (final link in links)
            SwitchListTile(
              key: ValueKey('overlay-garde-toggle-${link.id}'),
              contentPadding: EdgeInsets.zero,
              secondary: const Icon(
                Icons.circle,
                size: 12,
                color: AppColors.linked,
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
                        view: 'garde',
                        enabled: v,
                      );
                  ref
                    ..invalidate(overlayPreferencesProvider)
                    ..invalidate(overlayGardeEventsProvider)
                    ..invalidate(overlayGardeDatesProvider);
                } on ApiException catch (e) {
                  messenger.showSnackBar(
                    SnackBar(content: Text(e.localized(l))),
                  );
                }
              },
            ),
        ],
      ),
    );
  }
}

class _LegendItem extends StatelessWidget {
  const _LegendItem({
    required this.color,
    required this.label,
    this.icon,
    this.dashed = false,
    this.ring = false,
    this.marker,
    this.fill,
  });

  final Color color;
  final String label;
  final IconData? icon;
  final bool dashed;
  final bool ring;

  /// Marqueur non chromatique reproduit dans la puce (cdc §8) : une légende purement colorée
  /// n'explique rien à qui ne distingue pas les deux teintes.
  final String? marker;

  /// Fond de la puce quand elle reproduit l'aspect d'une case (bordure [color] + ce fond doux).
  final Color? fill;

  @override
  Widget build(BuildContext context) {
    // U-10 : puces rayon 4, texte 13, couleur label (#6F685C).
    Widget swatch;
    if (icon != null) {
      swatch = Icon(icon, size: 13, color: color);
    } else if (dashed) {
      swatch = SizedBox(
        width: 12,
        height: 12,
        child: CustomPaint(
          painter: _DashedRRectPainter(color: color, radius: 4),
        ),
      );
    } else if (ring) {
      swatch = Container(
        width: 12,
        height: 12,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(4),
          border: Border.all(color: color, width: 1.5),
        ),
      );
    } else if (marker != null) {
      // Puce reproduisant la case qu'elle explique : fond doux, bordure du parent, marqueur au
      // centre. `ink2` sur ces deux fonds vaut 7,55:1 et 6,74:1 — un blanc sur `accent` plafonnait
      // à 4,05:1, sous le seuil AA pour un texte de cette taille.
      swatch = Container(
        width: 16,
        height: 16,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: fill,
          borderRadius: BorderRadius.circular(5),
          border: Border.all(color: color),
        ),
        child: Text(
          marker!,
          style: const TextStyle(
            fontSize: 9,
            height: 1,
            fontWeight: FontWeight.w700,
            color: AppColors.ink2,
          ),
        ),
      );
    } else {
      swatch = Container(
        width: 12,
        height: 12,
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(4),
        ),
      );
    }
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        swatch,
        const SizedBox(width: 6),
        Text(
          label,
          style: const TextStyle(fontSize: 13, color: AppColors.label),
        ),
      ],
    );
  }
}

/// U-02 : bandeau crème résumant le rythme de garde (modèle + jour/heure d'échange).
class _RuleBanner extends StatelessWidget {
  const _RuleBanner({required this.pattern});

  final api.CustodyPattern pattern;

  static const _weekdayKeys = <int>[0, 1, 2, 3, 4, 5, 6];

  /// Le bandeau testait `'223'`, valeur qui n'existe nulle part : l'énumération
  /// serveur vaut `pattern_2_2_3`, et l'écran de réglages envoie déjà cette
  /// valeur-là. Un rythme 2-2-3 (ou `midweek_weekends`) tombait donc dans la branche
  /// par défaut et affichait sa **clé technique** à l'utilisateur (constat C28).
  String _kindLabel(AppL10n l) => switch (pattern.kind) {
    'alternating' => l.custodyKindAlternating,
    'pattern_2_2_3' => l.custodyKind223,
    'midweek_weekends' => l.custodyKindMidweek,
    'custom' => l.custodyKindCustom,
    // Repli localisé : ne jamais laisser fuir une valeur d'énumération à l'écran.
    _ => l.custodyKindUnknown,
  };

  String _weekday(AppL10n l) {
    final labels = <String>[
      l.weekdaySunday,
      l.weekdayMonday,
      l.weekdayTuesday,
      l.weekdayWednesday,
      l.weekdayThursday,
      l.weekdayFriday,
      l.weekdaySaturday,
    ];
    final idx = _weekdayKeys.contains(pattern.handoverWeekday)
        ? pattern.handoverWeekday
        : 0;
    return labels[idx].toLowerCase();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final text =
        '${_kindLabel(l)} · ${l.custodyRuleHandover(_weekday(l), pattern.handoverTime)}';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
      decoration: BoxDecoration(
        color: const Color(0xFFFBF4E8),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFEFE2C9)),
      ),
      child: Row(
        children: [
          const Icon(Icons.info_outline, size: 18, color: Color(0xFFC99A3F)),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(fontSize: 13, color: Color(0xFF6F685C)),
            ),
          ),
        ],
      ),
    );
  }
}

/// U-03 : période spéciale (vacances) en carte autonome.
class _HolidayCard extends StatelessWidget {
  const _HolidayCard({required this.holiday});

  final api.SchoolHoliday holiday;

  /// Genre de règle nommé plutôt que sa valeur d'énumération : la carte affichait
  /// `eve_alternating` tel quel (constat C31). Dictionnaire jumeau de celui du
  /// gabarit web (`garde.html.twig`).
  static String? ruleLabel(AppL10n l, String? kind) => switch (kind) {
    'alternating_weeks' => l.holidayRuleAlternatingWeeks,
    'split_50_50' => l.holidayRuleSplit5050,
    'fixed_parent' => l.holidayRuleFixedParent,
    'eve_alternating' => l.holidayRuleEveAlternating,
    'custom' => l.holidayRuleCustom,
    _ => null,
  };

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final rule = ruleLabel(l, holiday.ruleKind);

    return AppCard(
      radius: 16,
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  holiday.label,
                  style: AppTypography.textTheme.bodyLarge,
                ),
              ),
              Text(
                DateLabels.isoRange(
                  l.localeName,
                  holiday.startDate,
                  holiday.endDate,
                ),
                style: const TextStyle(fontSize: 12.5, color: AppColors.muted),
              ),
            ],
          ),
          const SizedBox(height: 4),
          // La barre A/B décorative a été retirée : deux `Expanded` donnaient
          // TOUJOURS un 50/50, ce qui laissait croire à un partage défini là où
          // aucune règle n'existe, et contredisait une période attribuée en entier à
          // un seul parent (`fixed_parent`). Même décision que côté web (constat C30).
          Text(
            rule ?? l.custodyHolidayNoRule,
            style: const TextStyle(fontSize: 12.5, color: AppColors.muted2),
          ),
        ],
      ),
    );
  }
}

class _Stats extends ConsumerWidget {
  const _Stats({required this.stats});

  final api.CustodyStats stats;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    // Les vrais noms, comme la légende juste au-dessus et comme les tuiles de stats
    // du web : l'écran annonçait « Parent A · 16 jours » sous une légende qui disait
    // « Sophie » (constat C32).
    final markers = ref.watch(parentMarkersProvider);
    final nameA = markers.nameA.isNotEmpty ? markers.nameA : l.custodyParentA;
    final nameB = markers.nameB.isNotEmpty ? markers.nameB : l.custodyParentB;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l.custodyStatsTitle.toUpperCase(), style: AppTypography.eyebrow),
          const SizedBox(height: 10),
          Text(
            '$nameA · ${l.custodyStatsDays(stats.parentACount)} (${stats.pctA} %)',
          ),
          const SizedBox(height: 4),
          Text(
            '$nameB · ${l.custodyStatsDays(stats.parentBCount)} (${stats.pctB} %)',
          ),
        ],
      ),
    );
  }
}

class _ExceptionTile extends ConsumerWidget {
  const _ExceptionTile({required this.exception});

  final api.CustodyException exception;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final family = ref.watch(familyContextProvider);
    final subtitle = exception.reason;

    return ListTile(
      contentPadding: EdgeInsets.zero,
      dense: true,
      title: Text(
        DateLabels.isoRange(
          l.localeName,
          exception.startDate,
          exception.endDate,
        ),
      ),
      subtitle: subtitle != null && subtitle.isNotEmpty ? Text(subtitle) : null,
      // Supprimer une exception est une mutation : sans `custody:edit` la corbeille
      // n'est plus offerte du tout (elle menait à un 403 — constat C29).
      trailing: !ref.watch(custodyCanEditProvider)
          ? null
          : IconButton(
              icon: const Icon(Icons.delete_outline, color: AppColors.error),
              tooltip: l.custodyDelete,
              onPressed: family == null
                  ? null
                  : () async {
                      final messenger = ScaffoldMessenger.of(context);
                      try {
                        await ref
                            .read(custodyRepositoryProvider)
                            .deleteException(family.familyId, exception.id);
                        ref.invalidate(familyCustodyExceptionsProvider);
                        ref.invalidate(familyCustodyDaysProvider);
                        ref.invalidate(familyCustodyStatsProvider);
                        messenger.showSnackBar(
                          SnackBar(content: Text(l.custodyExceptionDeleted)),
                        );
                      } on ApiException catch (e) {
                        messenger.showSnackBar(
                          SnackBar(content: Text(e.localized(l))),
                        );
                      }
                    },
            ),
    );
  }
}

/// Feuille de détail d'un jour (US-CUST-6), transposition mobile du fragment web
/// `_garde_day.html.twig` : parent **en clair**, origine de l'attribution, phrase de
/// transmission, activités du jour, puis les actions sous `custody:edit`.
///
/// Elle n'affichait que la date ISO brute, « Confié à : Parent A » et la source —
/// ni le nom que la légende affiche pourtant, ni l'heure de retour, qui est
/// l'information qu'on vient y chercher (constats C32, C55, C58).
class CustodyDaySheet extends ConsumerStatefulWidget {
  const CustodyDaySheet({super.key, required this.date});

  /// Jour consulté, en date civile `AAAA-MM-JJ`.
  final String date;

  @override
  ConsumerState<CustodyDaySheet> createState() => _CustodyDaySheetState();
}

class _CustodyDaySheetState extends ConsumerState<CustodyDaySheet> {
  bool _busy = false;
  String? _error;

  /// Origine de l'attribution, dans la priorité du moteur (exception > vacances >
  /// base) : l'écran ne doit jamais expliquer un jour autrement que le moteur.
  String _originLabel(AppL10n l, CustodyDayDetail detail) {
    final source = detail.day?.source;
    if (source == null) return l.custodyDayUnplanned;
    if (source == 'exception') {
      final reason = detail.exception?.reason;
      return reason == null || reason.isEmpty
          ? l.custodySourceException
          : '${l.custodySourceException} · $reason';
    }
    if (source == 'holiday') {
      final holiday = detail.holiday;
      final label = holiday?.label ?? l.custodySourceHoliday;
      final rule = _HolidayCard.ruleLabel(l, holiday?.ruleKind);
      return rule == null ? label : '$label · $rule';
    }
    // Une période de vacances SANS règle laisse le rythme s'appliquer : le dire ici
    // évite de croire à une erreur en voyant l'icône « vacances » sur la case.
    final holiday = detail.holiday;
    return holiday == null
        ? l.custodyRuleHabitual
        : '${l.custodyRuleHabitual} · ${l.custodyRuleDuring(holiday.label)}';
  }

  Future<void> _swap(String slot) async {
    final family = ref.read(familyContextProvider);
    final pattern = ref.read(familyCustodyPatternProvider).value;
    if (family == null || pattern == null) return;
    // Slot courant A → cible = parent B, et inversement.
    final target = slot == 'A'
        ? pattern.parentBMembershipId
        : pattern.parentAMembershipId;
    final l = AppL10n.of(context);
    if (target == null) {
      setState(() => _error = l.custodySwapUnavailable);
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    final swappedMsg = l.custodySwapped;
    final reason = l.custodySwapReason;
    try {
      await ref
          .read(custodyRepositoryProvider)
          .createException(
            family.familyId,
            startDate: widget.date,
            endDate: widget.date,
            parentMembershipId: target,
            reason: reason,
          );
      ref.invalidate(familyCustodyDaysProvider);
      ref.invalidate(familyCustodyStatsProvider);
      ref.invalidate(familyCustodyExceptionsProvider);
      if (navigator.canPop()) navigator.pop();
      messenger.showSnackBar(SnackBar(content: Text(swappedMsg)));
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _error = e.localized(l));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final detailAsync = ref.watch(custodyDayDetailProvider(widget.date));
    final date = CivilDate.of(widget.date);

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l.custodyDayDetailTitle, style: AppTypography.eyebrow),
            const SizedBox(height: 8),
            Text(
              date == null
                  ? widget.date
                  : DateLabels.longDate(l.localeName, date),
              key: const ValueKey('custody-day-title'),
              style: AppTypography.textTheme.titleLarge,
            ),
            const SizedBox(height: 12),
            detailAsync.when(
              loading: () => const Padding(
                padding: EdgeInsets.symmetric(vertical: 24),
                child: Center(child: CircularProgressIndicator()),
              ),
              error: (e, _) => ErrorBanner(errorText(context, e)),
              data: (detail) => _body(l, detail),
            ),
          ],
        ),
      ),
    );
  }

  Widget _body(AppL10n l, CustodyDayDetail detail) {
    final markers = ref.watch(parentMarkersProvider);
    final slot = detail.slot;
    final canEdit = ref.watch(custodyCanEditProvider);
    final canSeeAgenda = ref.watch(agendaCanViewProvider);

    String nameOf(String s) {
      final name = markers.nameForSlot(s);
      return name.isNotEmpty
          ? name
          : (s == 'A' ? l.custodyParentA : l.custodyParentB);
    }

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          _originLabel(l, detail),
          style: const TextStyle(fontSize: 13, color: AppColors.muted),
        ),
        const SizedBox(height: 14),
        if (slot == null)
          Text(l.custodyDayUnassigned)
        else ...[
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              // L'initiale reprend le marqueur des cases (cdc §8.1) : le même parent
              // porte le même marqueur partout, et il reste lisible sans la couleur.
              Container(
                width: 34,
                height: 34,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: slot == 'A'
                      ? AppColors.accentSoft2
                      : AppColors.positiveSoft,
                  borderRadius: BorderRadius.circular(11),
                  border: Border.all(
                    color: slot == 'A' ? AppColors.accent : AppColors.positive,
                  ),
                ),
                child: Text(
                  markers.markerForSlot(slot),
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ink2,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(l.custodyAtParent, style: AppTypography.eyebrow),
                    Text(
                      nameOf(slot),
                      key: const ValueKey('custody-day-parent'),
                      style: AppTypography.textTheme.bodyLarge,
                    ),
                  ],
                ),
              ),
            ],
          ),
          // « Retour chez {Parent} le {date} à {heure} » (US-CUST-6), dérivée des
          // jours **matérialisés** qui suivent. En bout d'horizon glissant aucun
          // changement ne suit : la phrase est alors omise plutôt qu'inventée.
          if (detail.hasHandover) ...[
            const SizedBox(height: 8),
            Text(
              l.custodyHandover(
                nameOf(detail.handoverSlot!),
                DateLabels.isoDayMonthYear(l.localeName, detail.handoverDate!),
                detail.handoverTime!,
              ),
              key: const ValueKey('custody-day-handover'),
              style: const TextStyle(fontSize: 13, color: AppColors.label),
            ),
          ],
        ],
        // Activités du jour — domaine `agenda`. Section omise sans le droit : son
        // état vide dirait déjà s'il y a ou non des activités, information que ce
        // membre n'a pas à obtenir par cet écran.
        if (canSeeAgenda) ...[
          const SizedBox(height: 18),
          Text(l.custodyDayActivities, style: AppTypography.eyebrow),
          const SizedBox(height: 6),
          _DayActivities(date: widget.date),
        ],
        if (_error != null) ...[
          const SizedBox(height: 12),
          ErrorBanner(_error!),
        ],
        // Actions : `custody:edit` seulement (US-CUST-6, « je vois l'info mais pas
        // les actions d'édition »).
        if (canEdit) ...[
          const SizedBox(height: 18),
          Row(
            children: [
              if (slot != null)
                Expanded(
                  child: Tooltip(
                    message: detail.isPast ? l.custodyPastNoSwap : '',
                    child: FilledButton.icon(
                      key: const ValueKey('custody-swap-day'),
                      // L'API refuse une exception rétroactive (422) : le bouton est
                      // présenté inerte avec sa raison, plutôt que d'offrir une
                      // action qui échoue.
                      onPressed: _busy || detail.isPast
                          ? null
                          : () => _swap(slot),
                      icon: const Icon(Icons.swap_horiz),
                      label: Text(l.custodySwapDay),
                    ),
                  ),
                ),
              if (slot != null) const SizedBox(width: 10),
              OutlinedButton(
                key: const ValueKey('custody-day-settings'),
                onPressed: () {
                  Navigator.of(context).pop();
                  Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => const CustodySettingsScreen(),
                    ),
                  );
                },
                child: Text(l.custodyEditPattern),
              ),
            ],
          ),
        ],
      ],
    );
  }
}

/// Activités couvrant le jour affiché, dans l'ordre du serveur (journée entière
/// d'abord, puis l'heure). Même index que les pastilles des cases : un jour n'a pas
/// à afficher ici une activité que sa case ne montrait pas.
class _DayActivities extends ConsumerWidget {
  const _DayActivities({required this.date});

  final String date;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);

    return ref
        .watch(custodyMonthActivitiesProvider)
        .maybeWhen(
          data: (byDate) {
            final events = byDate[date] ?? const <api.Event>[];
            if (events.isEmpty) {
              return Text(
                l.custodyDayActivitiesEmpty,
                style: const TextStyle(fontSize: 13, color: AppColors.muted),
              );
            }

            return Column(
              key: const ValueKey('custody-day-events'),
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final e in events)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 6),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Container(
                          width: 3,
                          height: 26,
                          decoration: BoxDecoration(
                            color: agendaCategoryColor(e.category),
                            borderRadius: BorderRadius.circular(2),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                e.title,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(fontSize: 13.5),
                              ),
                              Text(
                                _timeLabel(l, e),
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: AppColors.muted,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        // La catégorie est NOMMÉE, pas seulement colorée : la barre de
                        // gauche ne survit ni aux niveaux de gris ni au contraste forcé
                        // (cdc §8.1, même raison que le marqueur A/B).
                        CategoryPill(category: e.category),
                      ],
                    ),
                  ),
              ],
            );
          },
          orElse: () => const SizedBox.shrink(),
        );
  }

  String _timeLabel(AppL10n l, api.Event e) {
    if (e.allDay) return l.custodyAllDay;
    final start = CivilDate.hm(e.startsAt);
    final end = e.endsAt == null ? '' : CivilDate.hm(e.endsAt!);
    return end.isEmpty ? start : '$start – $end';
  }
}
