import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/util/civil_date.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../core/widgets/large_title_header.dart';
import '../../../l10n/app_localizations.dart';
import '../../agenda/data/agenda_repository.dart';
import '../../agenda/data/overlay_repository.dart';
import '../../family/application/family_context.dart';
import '../data/custody_repository.dart';
import 'custody_settings_screen.dart';
import 'parent_initials.dart';

/// US-CUST-5/6 — écran Garde mobile : calendrier mensuel coloré par parent
/// (terracotta = A, teal = B), jours exceptionnels distingués, statistiques,
/// exceptions et vacances. Lecture du **mois courant** (défaut serveur).
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

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            // U-08 : grand titre inline « Planning de garde » + accès au rythme.
            LargeTitleHeader(
              title: l.custodyScreenTitle,
              trailing: IconButton(
                key: const ValueKey('custody-settings-action'),
                icon: const Icon(Icons.tune),
                tooltip: l.custodySettingsTitle,
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => const CustodySettingsScreen(),
                  ),
                ),
              ),
            ),
            Expanded(
              child: patternAsync.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (e, _) => Padding(
                  padding: const EdgeInsets.all(20),
                  child: ErrorBanner(
                    e is ApiException ? e.message : e.toString(),
                  ),
                ),
                data: (pattern) => RefreshIndicator(
                  onRefresh: () async {
                    ref.invalidate(familyCustodyPatternProvider);
                    ref.invalidate(familyCustodyDaysProvider);
                    ref.invalidate(familyCustodyStatsProvider);
                    ref.invalidate(familyCustodyExceptionsProvider);
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

class _NoPattern extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(24),
      children: [
        const SizedBox(height: 40),
        Center(child: Text(l.custodyNoPattern)),
        const SizedBox(height: 16),
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
    // U-09 : jours porteurs d'un événement de la famille active (pastille d'activité).
    final activityDates = ref
        .watch(familyEventsProvider)
        .maybeWhen(
          data: (result) => {
            for (final e in result.items)
              if (CivilDate.ymd(e.startsAt).isNotEmpty)
                CivilDate.ymd(e.startsAt),
          },
          orElse: () => const <String>{},
        );

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
          error: (e, _) =>
              ErrorBanner(e is ApiException ? e.message : e.toString()),
          data: (days) => _Calendar(
            pattern: pattern,
            days: days,
            overlayDates: overlayDates,
            activityDates: activityDates,
          ),
        ),
        const SizedBox(height: 12),
        const _Legend(),
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
          error: (e, _) =>
              ErrorBanner(e is ApiException ? e.message : e.toString()),
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
            _ImportHolidaysButton(),
          ],
        ),
        const SizedBox(height: 8),
        holidaysAsync.when(
          loading: () => const SizedBox.shrink(),
          error: (e, _) =>
              ErrorBanner(e is ApiException ? e.message : e.toString()),
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
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
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
    this.activityDates = const {},
  });

  final api.CustodyPattern pattern;
  final List<api.CustodyDay> days;
  final Set<String> overlayDates;
  final Set<String> activityDates;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    if (days.isEmpty) return Text(l.custodyNoPattern);

    final byDate = {for (final d in days) d.date: d};
    final parsed = days.map((d) => DateTime.parse(d.date)).toList()..sort();
    final first = parsed.first;
    final last = parsed.last;
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
      cells.add(
        _DayCell(
          dateKey: key,
          dayNumber: cursor.day,
          day: day,
          slot: _slotFor(day),
          isToday: key == todayKey,
          hasOverlay: day != null && overlayDates.contains(key),
          hasActivity: day != null && activityDates.contains(key),
        ),
      );
      cursor = DateTime(cursor.year, cursor.month, cursor.day + 1);
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // U-01 : navigation de mois (chevrons présentation — la fenêtre est le
        // mois courant côté serveur ; le fenêtrage par mois relève du backend).
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              _monthLabel(context, first),
              style: AppTypography.textTheme.titleMedium,
            ),
            const Row(
              children: [
                _MonthNavButton(icon: Icons.chevron_left),
                SizedBox(width: 8),
                _MonthNavButton(icon: Icons.chevron_right),
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

  String _monthLabel(BuildContext context, DateTime d) {
    const months = [
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
    return '${months[d.month]} ${d.year}';
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
class _MonthNavButton extends StatelessWidget {
  const _MonthNavButton({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 34,
      height: 34,
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.border2),
      ),
      child: Icon(icon, size: 20, color: AppColors.muted),
    );
  }
}

class _DayCell extends ConsumerWidget {
  const _DayCell({
    required this.dateKey,
    required this.dayNumber,
    required this.day,
    required this.slot,
    this.isToday = false,
    this.hasOverlay = false,
    this.hasActivity = false,
  });

  final String dateKey;
  final int dayNumber;
  final api.CustodyDay? day;
  final String? slot;
  final bool isToday;
  final bool hasOverlay;
  final bool hasActivity;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final inMonth = day != null;
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
          // (vacances bas-droite, activité bas-centre, famille liée bas-droite). Miroir du web
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
          // U-09 : pastille d'activité (événement de la famille active), bas-centre.
          if (hasActivity)
            const Align(
              alignment: Alignment.bottomCenter,
              child: Padding(
                padding: EdgeInsets.only(bottom: 1),
                child: _ActivityDot(),
              ),
            ),
          // U-11 : marqueur famille liée (overlay §3.3) = anneau bas-droite.
          if (hasOverlay)
            Align(
              alignment: Alignment.bottomRight,
              child: _OverlayDot(key: ValueKey('overlay-dot-$dateKey')),
            ),
        ],
      ),
    );

    if (!inMonth || slot == null) {
      return cell;
    }

    return InkWell(
      key: ValueKey('garde-day-$dateKey'),
      borderRadius: BorderRadius.circular(12),
      onTap: () => _openDetail(context, ref),
      child: cell,
    );
  }

  void _openDetail(BuildContext context, WidgetRef ref) {
    showModalBottomSheet<void>(
      context: context,
      builder: (_) => CustodyDayDetail(date: dateKey, slot: slot!, day: day!),
    );
  }
}

class _Legend extends ConsumerWidget {
  const _Legend();

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

/// U-09 : pastille pleine d'activité (événement de la famille active).
class _ActivityDot extends StatelessWidget {
  const _ActivityDot();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 5,
      height: 5,
      decoration: const BoxDecoration(
        color: AppColors.ink3,
        shape: BoxShape.circle,
      ),
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
                  messenger.showSnackBar(SnackBar(content: Text(e.message)));
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

  String _kindLabel(AppL10n l) => switch (pattern.kind) {
    'alternating' => l.custodyKindAlternating,
    '223' => l.custodyKind223,
    'custom' => l.custodyKindCustom,
    _ => pattern.kind,
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

/// U-03 : période spéciale (vacances) en carte autonome + barre de répartition A/B.
class _HolidayCard extends StatelessWidget {
  const _HolidayCard({required this.holiday});

  final api.SchoolHoliday holiday;

  @override
  Widget build(BuildContext context) {
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
                '${holiday.startDate} → ${holiday.endDate}',
                style: const TextStyle(fontSize: 12.5, color: AppColors.muted),
              ),
            ],
          ),
          if (holiday.ruleKind != null && holiday.ruleKind!.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              holiday.ruleKind!,
              style: const TextStyle(fontSize: 12.5, color: AppColors.muted2),
            ),
          ],
          const SizedBox(height: 10),
          // Barre de répartition A/B (illustration du partage de la période).
          ClipRRect(
            borderRadius: BorderRadius.circular(5),
            child: const Row(
              children: [
                Expanded(
                  child: ColoredBox(
                    color: AppColors.accent,
                    child: SizedBox(height: 8),
                  ),
                ),
                Expanded(
                  child: ColoredBox(
                    color: AppColors.positive,
                    child: SizedBox(height: 8),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Stats extends StatelessWidget {
  const _Stats({required this.stats});

  final api.CustodyStats stats;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l.custodyStatsTitle.toUpperCase(), style: AppTypography.eyebrow),
          const SizedBox(height: 10),
          Text(
            '${l.custodyParentA} · ${l.custodyStatsDays(stats.parentACount)} (${stats.pctA} %)',
          ),
          const SizedBox(height: 4),
          Text(
            '${l.custodyParentB} · ${l.custodyStatsDays(stats.parentBCount)} (${stats.pctB} %)',
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
      title: Text('${exception.startDate} → ${exception.endDate}'),
      subtitle: subtitle != null && subtitle.isNotEmpty ? Text(subtitle) : null,
      trailing: IconButton(
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
                  messenger.showSnackBar(SnackBar(content: Text(e.message)));
                }
              },
      ),
    );
  }
}

/// Détail d'un jour + « Échanger ce jour » (crée une exception d'un jour vers
/// l'autre parent). Le parent cible est déduit du rythme (slot A↔B).
class CustodyDayDetail extends ConsumerStatefulWidget {
  const CustodyDayDetail({
    super.key,
    required this.date,
    required this.slot,
    required this.day,
  });

  final String date;
  final String slot;
  final api.CustodyDay day;

  @override
  ConsumerState<CustodyDayDetail> createState() => _CustodyDayDetailState();
}

class _CustodyDayDetailState extends ConsumerState<CustodyDayDetail> {
  bool _busy = false;
  String? _error;

  String _sourceLabel(AppL10n l) => switch (widget.day.source) {
    'exception' => l.custodySourceException,
    'holiday' => l.custodySourceHoliday,
    _ => l.custodySourceBase,
  };

  Future<void> _swap() async {
    final family = ref.read(familyContextProvider);
    final pattern = ref.read(familyCustodyPatternProvider).value;
    if (family == null || pattern == null) return;
    // Slot courant A → cible = parent B, et inversement.
    final target = widget.slot == 'A'
        ? pattern.parentBMembershipId
        : pattern.parentAMembershipId;
    if (target == null) {
      setState(() => _error = AppL10n.of(context).custodySwapUnavailable);
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    final swappedMsg = AppL10n.of(context).custodySwapped;
    try {
      await ref
          .read(custodyRepositoryProvider)
          .createException(
            family.familyId,
            startDate: widget.date,
            endDate: widget.date,
            parentMembershipId: target,
            reason: 'Échange',
          );
      ref.invalidate(familyCustodyDaysProvider);
      ref.invalidate(familyCustodyStatsProvider);
      ref.invalidate(familyCustodyExceptionsProvider);
      if (navigator.canPop()) navigator.pop();
      messenger.showSnackBar(SnackBar(content: Text(swappedMsg)));
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _error = e.message);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l.custodyDayDetailTitle, style: AppTypography.eyebrow),
            const SizedBox(height: 8),
            Text(widget.date, style: AppTypography.textTheme.titleLarge),
            const SizedBox(height: 8),
            Text(
              '${l.custodyAssignedTo} : ${widget.slot == 'A' ? l.custodyParentA : l.custodyParentB}',
            ),
            Text('${l.custodySource} : ${_sourceLabel(l)}'),
            const SizedBox(height: 16),
            if (_error != null) ...[
              ErrorBanner(_error!),
              const SizedBox(height: 12),
            ],
            FilledButton.icon(
              key: const ValueKey('custody-swap-day'),
              onPressed: _busy ? null : _swap,
              icon: const Icon(Icons.swap_horiz),
              label: Text(l.custodySwapDay),
            ),
          ],
        ),
      ),
    );
  }
}
