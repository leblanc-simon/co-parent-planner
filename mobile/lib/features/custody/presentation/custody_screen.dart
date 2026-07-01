import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../../family/application/family_context.dart';
import '../data/custody_repository.dart';
import 'custody_settings_screen.dart';

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
        appBar: AppBar(title: Text(l.custodyTitle)),
        body: Center(child: Text(l.custodyIntact)),
      );
    }

    final patternAsync = ref.watch(familyCustodyPatternProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(l.custodyTitle),
        actions: [
          IconButton(
            key: const ValueKey('custody-settings-action'),
            icon: const Icon(Icons.tune),
            tooltip: l.custodySettingsTitle,
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => const CustodySettingsScreen(),
              ),
            ),
          ),
        ],
      ),
      body: patternAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Padding(
          padding: const EdgeInsets.all(20),
          child: ErrorBanner(e is ApiException ? e.message : e.toString()),
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
    );
  }
}

class _NoPattern extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return ListView(
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

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        daysAsync.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) =>
              ErrorBanner(e is ApiException ? e.message : e.toString()),
          data: (days) => _Calendar(pattern: pattern, days: days),
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
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        dense: true,
                        leading: const Icon(
                          Icons.beach_access,
                          color: AppColors.catSchool,
                        ),
                        title: Text(h.label),
                        subtitle: Text('${h.startDate} → ${h.endDate}'),
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
  const _Calendar({required this.pattern, required this.days});

  final api.CustodyPattern pattern;
  final List<api.CustodyDay> days;

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
    final gridStart = DateTime(first.year, first.month, first.day - (first.weekday - 1));
    final gridEnd = DateTime(last.year, last.month, last.day + (7 - last.weekday));

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
        ),
      );
      cursor = DateTime(cursor.year, cursor.month, cursor.day + 1);
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          _monthLabel(context, first),
          style: AppTypography.textTheme.titleMedium,
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
        label,
        style: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: AppColors.muted,
        ),
      ),
    );
  }
}

class _DayCell extends ConsumerWidget {
  const _DayCell({
    required this.dateKey,
    required this.dayNumber,
    required this.day,
    required this.slot,
  });

  final String dateKey;
  final int dayNumber;
  final api.CustodyDay? day;
  final String? slot;

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

    final cell = Container(
      decoration: BoxDecoration(
        color: inMonth ? bg : Colors.transparent,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: inMonth ? borderColor : Colors.transparent,
          width: isException ? 2 : 1,
        ),
      ),
      padding: const EdgeInsets.all(4),
      child: Stack(
        children: [
          Align(
            alignment: Alignment.topLeft,
            child: Text(
              '$dayNumber',
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: inMonth ? AppColors.ink : AppColors.muted2,
              ),
            ),
          ),
          if (isException)
            const Align(
              alignment: Alignment.bottomRight,
              child: Icon(Icons.swap_horiz, size: 11, color: AppColors.ink3),
            )
          else if (isHoliday)
            const Align(
              alignment: Alignment.bottomRight,
              child: Icon(
                Icons.beach_access,
                size: 11,
                color: AppColors.catSchool,
              ),
            ),
        ],
      ),
    );

    if (!inMonth || slot == null) {
      return cell;
    }

    return InkWell(
      key: ValueKey('garde-day-$dateKey'),
      borderRadius: BorderRadius.circular(8),
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

class _Legend extends StatelessWidget {
  const _Legend();

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return Wrap(
      spacing: 16,
      runSpacing: 6,
      children: [
        _LegendItem(color: AppColors.accent, label: l.custodyParentA),
        _LegendItem(color: AppColors.positive, label: l.custodyParentB),
        _LegendItem(
          color: AppColors.ink3,
          label: l.custodyExceptional,
          icon: Icons.swap_horiz,
        ),
        _LegendItem(
          color: AppColors.catSchool,
          label: l.custodyHoliday,
          icon: Icons.beach_access,
        ),
      ],
    );
  }
}

class _LegendItem extends StatelessWidget {
  const _LegendItem({required this.color, required this.label, this.icon});

  final Color color;
  final String label;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        icon != null
            ? Icon(icon, size: 13, color: color)
            : Container(
                width: 12,
                height: 12,
                decoration: BoxDecoration(
                  color: color,
                  borderRadius: BorderRadius.circular(3),
                ),
              ),
        const SizedBox(width: 6),
        Text(label, style: const TextStyle(fontSize: 12.5)),
      ],
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
