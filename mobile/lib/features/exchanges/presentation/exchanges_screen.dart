import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../core/widgets/initial_avatar.dart';
import '../../../core/widgets/large_title_header.dart';
import '../../../l10n/app_localizations.dart';
import '../../children/data/children_repository.dart';
import '../../family/application/family_context.dart';
import '../data/exchanges_repository.dart';
import 'exchange_detail_screen.dart';

/// Filtre de statut actif (`open` par défaut ; `decided` pour l'archive). Le
/// filtrage est **client** (le client généré n'expose pas `?status=`).
final _statusFilterProvider = StateProvider.autoDispose<String>(
  (ref) => 'open',
);

/// Libellé français d'une date `YYYY-MM-DD` (jamais de date codée en dur).
String frenchDate(String? isoDate) {
  if (isoDate == null || isoDate.length < 10) return '';
  final d = DateTime.tryParse(isoDate.substring(0, 10));
  if (d == null) return '';
  const months = [
    '',
    'janv.',
    'févr.',
    'mars',
    'avr.',
    'mai',
    'juin',
    'juil.',
    'août',
    'sept.',
    'oct.',
    'nov.',
    'déc.',
  ];
  return '${d.day} ${months[d.month]} ${d.year}';
}

/// US-EX-4/8 — écran liste des échanges de la famille active.
///
/// Filtres de statut (Ouverts/Décidés), cartes (type, titre, rattachement,
/// auteur, échéance, marqueur « en retard », nombre de messages), état vide,
/// FAB de création. Tri : ouverts d'abord (§3.6). Rafraîchissement par
/// pull-to-refresh — le live SSE mobile est différé (cohérent Lots 3/4).
class ExchangesScreen extends ConsumerWidget {
  const ExchangesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final family = ref.watch(familyContextProvider);
    final exchangesAsync = ref.watch(familyExchangesProvider);
    final status = ref.watch(_statusFilterProvider);

    return Scaffold(
      floatingActionButton: family == null
          ? null
          : FloatingActionButton.extended(
              key: const ValueKey('exchange-new'),
              backgroundColor: AppColors.ink,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(AppRadii.nav),
              ),
              onPressed: () => _openForm(context),
              icon: const Icon(Icons.add),
              label: Text(l.exchangesNew),
            ),
      body: SafeArea(
        child: Column(
          children: [
            LargeTitleHeader(title: l.exchangesTitle),
            Expanded(
              child: exchangesAsync.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (e, _) => Padding(
                  padding: const EdgeInsets.all(20),
                  child: ErrorBanner(
                    e is ApiException ? e.message : e.toString(),
                  ),
                ),
                data: (all) {
                  final items = sortExchanges(filterByStatus(all, status));
                  return RefreshIndicator(
                    onRefresh: () async {
                      ref.invalidate(familyExchangesProvider);
                      await ref.read(familyExchangesProvider.future);
                    },
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
                      children: [
                        const _StatusFilters(),
                        const SizedBox(height: 8),
                        if (items.isEmpty) ...[
                          const SizedBox(height: 40),
                          Center(child: Text(l.exchangesEmpty)),
                        ] else
                          for (final e in items)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: _ExchangeCard(exchange: e),
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

  static void _openForm(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _ExchangeForm(),
    );
  }
}

/// Rangée de filtres de statut (Ouverts / Décidés).
class _StatusFilters extends ConsumerWidget {
  const _StatusFilters();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final selected = ref.watch(_statusFilterProvider);
    final entries = <(String, String)>[
      ('open', l.exchangesFilterOpen),
      ('decided', l.exchangesFilterDecided),
    ];
    return Wrap(
      spacing: 8,
      children: [
        for (final (value, label) in entries)
          _FilterChip(
            label: label,
            selected: selected == value,
            onTap: () => ref.read(_statusFilterProvider.notifier).state = value,
          ),
      ],
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadii.pill),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
        decoration: BoxDecoration(
          color: selected
              ? AppColors.ink.withValues(alpha: 0.10)
              : AppColors.card,
          borderRadius: BorderRadius.circular(AppRadii.pill),
          border: Border.all(
            color: selected ? AppColors.ink : AppColors.border2,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: selected ? AppColors.ink : AppColors.muted,
          ),
        ),
      ),
    );
  }
}

/// Carte d'un échange dans la liste (US-EX-4/8). Ouvre le détail au tap.
class _ExchangeCard extends ConsumerWidget {
  const _ExchangeCard({required this.exchange});

  final api.Exchange exchange;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final isDecision = exchange.type == 'decision';

    final children = ref
        .watch(familyChildrenProvider)
        .maybeWhen(data: (c) => c, orElse: () => const <api.Child>[]);
    String target = l.exchangeWholeFamily;
    if (exchange.childId != null) {
      for (final c in children) {
        if (c.id == exchange.childId) {
          target = c.firstName;
          break;
        }
      }
    }

    return InkWell(
      key: ValueKey('exchange-card-${exchange.id}'),
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => ExchangeDetailScreen(exchangeId: exchange.id),
        ),
      ),
      borderRadius: BorderRadius.circular(16),
      child: AppCard(
        radius: 16,
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                _TypePill(isDecision: isDecision),
                const SizedBox(width: 8),
                if (exchange.status == 'decided')
                  _StatusTag(
                    label: l.exchangeDecided,
                    fg: AppColors.positiveStrong,
                    bg: AppColors.positiveSoft,
                  )
                else if (exchange.overdue)
                  _StatusTag(
                    label: l.exchangeOverdue,
                    fg: AppColors.accentStrong,
                    bg: AppColors.accentSoft2,
                    icon: Icons.warning_amber_rounded,
                  ),
                const Spacer(),
                Text(
                  target,
                  style: const TextStyle(fontSize: 12, color: AppColors.muted2),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              exchange.title,
              style: const TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w600,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                InitialAvatar.from(
                  hexColor: exchange.authorColor,
                  label: exchange.authorName ?? l.exchangeAnonymous,
                  size: 22,
                ),
                const SizedBox(width: 6),
                Flexible(
                  child: Text(
                    exchange.authorName ?? l.exchangeAnonymous,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 13,
                      color: AppColors.muted,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                const Icon(
                  Icons.chat_bubble_outline,
                  size: 14,
                  color: AppColors.muted2,
                ),
                const SizedBox(width: 4),
                Text(
                  '${exchange.messageCount}',
                  style: const TextStyle(fontSize: 13, color: AppColors.muted),
                ),
                if (exchange.deadline != null) ...[
                  const SizedBox(width: 10),
                  const Icon(
                    Icons.event_outlined,
                    size: 14,
                    color: AppColors.muted2,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    frenchDate(exchange.deadline),
                    style: TextStyle(
                      fontSize: 13,
                      color: exchange.overdue
                          ? AppColors.accentStrong
                          : AppColors.muted,
                      fontWeight: exchange.overdue
                          ? FontWeight.w600
                          : FontWeight.w400,
                    ),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Pastille du type d'échange (Discussion / Décision).
class _TypePill extends StatelessWidget {
  const _TypePill({required this.isDecision});

  final bool isDecision;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final (fg, bg, label) = isDecision
        ? (AppColors.accent, AppColors.accentSoft2, l.exchangeTypeDecision)
        : (
            AppColors.catSport,
            AppColors.catSportSoft,
            l.exchangeTypeDiscussion,
          );
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(
        label,
        style: TextStyle(color: fg, fontSize: 11, fontWeight: FontWeight.w600),
      ),
    );
  }
}

class _StatusTag extends StatelessWidget {
  const _StatusTag({
    required this.label,
    required this.fg,
    required this.bg,
    this.icon,
  });

  final String label;
  final Color fg;
  final Color bg;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 12, color: fg),
            const SizedBox(width: 3),
          ],
          Text(
            label,
            style: TextStyle(
              color: fg,
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

/// US-EX-1 — formulaire de création d'un échange (discussion ou décision).
/// La validation locale est minimale ; le serveur fait autorité.
class _ExchangeForm extends ConsumerStatefulWidget {
  const _ExchangeForm();

  @override
  ConsumerState<_ExchangeForm> createState() => _ExchangeFormState();
}

class _ExchangeFormState extends ConsumerState<_ExchangeForm> {
  final _title = TextEditingController();
  final _body = TextEditingController();
  String _type = 'discussion';
  bool _wholeFamily = true;
  String? _childId;
  DateTime? _deadline;
  final _options = <TextEditingController>[
    TextEditingController(),
    TextEditingController(),
  ];
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _title.dispose();
    _body.dispose();
    for (final c in _options) {
      c.dispose();
    }
    super.dispose();
  }

  bool get _canSubmit {
    if (_title.text.trim().isEmpty) return false;
    if (_type == 'decision') {
      final filled = _options.where((c) => c.text.trim().isNotEmpty).length;
      if (filled < 2) return false;
    }
    return true;
  }

  Future<void> _pickDeadline() async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: _deadline ?? now,
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 3),
    );
    if (date == null || !mounted) return;
    setState(() => _deadline = date);
  }

  Future<void> _submit() async {
    final family = ref.read(familyContextProvider);
    if (family == null) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    final createdMsg = AppL10n.of(context).exchangeCreated;
    try {
      await ref
          .read(exchangesRepositoryProvider)
          .create(
            family.familyId,
            type: _type,
            title: _title.text.trim(),
            body: _body.text.trim().isEmpty ? null : _body.text.trim(),
            childId: _wholeFamily ? null : _childId,
            deadline: _deadline == null ? null : _dateOnly(_deadline!),
            options: _type == 'decision'
                ? [
                    for (final c in _options)
                      if (c.text.trim().isNotEmpty)
                        (label: c.text.trim(), sub: null),
                  ]
                : null,
          );
      ref.invalidate(familyExchangesProvider);
      if (navigator.canPop()) navigator.pop();
      messenger.showSnackBar(SnackBar(content: Text(createdMsg)));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final children = ref
        .watch(familyChildrenProvider)
        .maybeWhen(data: (c) => c, orElse: () => const <api.Child>[]);
    _childId ??= children.isNotEmpty ? children.first.id : null;

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
              Text(l.exchangesNew, style: AppTypography.textTheme.titleLarge),
              const SizedBox(height: 12),
              if (_error != null) ...[
                ErrorBanner(_error!),
                const SizedBox(height: 12),
              ],
              SegmentedButton<String>(
                segments: [
                  ButtonSegment(
                    value: 'discussion',
                    label: Text(l.exchangeTypeDiscussion),
                  ),
                  ButtonSegment(
                    value: 'decision',
                    label: Text(l.exchangeTypeDecision),
                  ),
                ],
                selected: {_type},
                onSelectionChanged: (s) => setState(() => _type = s.first),
              ),
              const SizedBox(height: 12),
              TextField(
                key: const ValueKey('exchange-title'),
                controller: _title,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(labelText: l.exchangeFieldTitle),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _body,
                minLines: 2,
                maxLines: 4,
                decoration: InputDecoration(labelText: l.exchangeFieldBody),
              ),
              const SizedBox(height: 4),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(l.exchangeWholeFamily),
                value: _wholeFamily,
                onChanged: (v) => setState(() => _wholeFamily = v),
              ),
              if (!_wholeFamily && children.isNotEmpty)
                DropdownButtonFormField<String>(
                  initialValue: _childId,
                  decoration: InputDecoration(labelText: l.exchangeChooseChild),
                  items: [
                    for (final c in children)
                      DropdownMenuItem(value: c.id, child: Text(c.firstName)),
                  ],
                  onChanged: (v) => setState(() => _childId = v),
                ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(l.exchangeFieldDeadline),
                subtitle: Text(
                  _deadline == null
                      ? l.exchangeNoDeadline
                      : frenchDate(_dateOnly(_deadline!)),
                ),
                trailing: _deadline == null
                    ? const Icon(Icons.event)
                    : IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () => setState(() => _deadline = null),
                      ),
                onTap: _pickDeadline,
              ),
              if (_type == 'decision') ...[
                const SizedBox(height: 8),
                Text(l.exchangeFieldOptions, style: AppTypography.eyebrow),
                const SizedBox(height: 4),
                for (var i = 0; i < _options.length; i++)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Row(
                      children: [
                        Expanded(
                          child: TextField(
                            key: ValueKey('exchange-option-$i'),
                            controller: _options[i],
                            onChanged: (_) => setState(() {}),
                            decoration: InputDecoration(
                              labelText: '${l.exchangeOptionLabel} ${i + 1}',
                            ),
                          ),
                        ),
                        if (_options.length > 2)
                          IconButton(
                            icon: const Icon(Icons.remove_circle_outline),
                            onPressed: () =>
                                setState(() => _options.removeAt(i).dispose()),
                          ),
                      ],
                    ),
                  ),
                Align(
                  alignment: Alignment.centerLeft,
                  child: TextButton.icon(
                    key: const ValueKey('exchange-add-option'),
                    onPressed: () =>
                        setState(() => _options.add(TextEditingController())),
                    icon: const Icon(Icons.add, size: 18),
                    label: Text(l.exchangeAddOption),
                  ),
                ),
                Text(
                  l.exchangeOptionsHint,
                  style: const TextStyle(fontSize: 12, color: AppColors.muted2),
                ),
              ],
              const SizedBox(height: 16),
              FilledButton(
                key: const ValueKey('exchange-submit'),
                onPressed: _busy || !_canSubmit ? null : _submit,
                child: Text(l.exchangeCreate),
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String _dateOnly(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-'
      '${d.month.toString().padLeft(2, '0')}-'
      '${d.day.toString().padLeft(2, '0')}';
}
