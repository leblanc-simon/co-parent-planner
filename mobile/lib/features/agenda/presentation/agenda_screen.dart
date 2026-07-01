import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../../children/data/children_repository.dart';
import '../../family/application/family_context.dart';
import '../../members/data/members_repository.dart';
import '../data/agenda_repository.dart';

/// US-AG-3/9 — écran Agenda mobile : occurrences du mois courant groupées par jour,
/// avec pastilles de catégorie (§8). Création d'événements **ponctuels** via un
/// formulaire ; les séries s'affichent mais leur création/édition par portée reste
/// web au Lot 4 (offline riche consolidé au Lot 8).
class AgendaScreen extends ConsumerWidget {
  const AgendaScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final family = ref.watch(familyContextProvider);
    final eventsAsync = ref.watch(familyEventsProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l.agendaTitle)),
      floatingActionButton: family == null
          ? null
          : FloatingActionButton.extended(
              key: const ValueKey('agenda-new'),
              onPressed: () => _openForm(context),
              icon: const Icon(Icons.add),
              label: Text(l.agendaNew),
            ),
      body: eventsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Padding(
          padding: const EdgeInsets.all(20),
          child: ErrorBanner(e is ApiException ? e.message : e.toString()),
        ),
        data: (events) => RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(familyEventsProvider);
            await ref.read(familyEventsProvider.future);
          },
          child: events.isEmpty
              ? ListView(
                  padding: const EdgeInsets.all(24),
                  children: [
                    const SizedBox(height: 40),
                    Center(child: Text(l.agendaEmpty)),
                  ],
                )
              : _EventList(events: events),
        ),
      ),
    );
  }

  static void _openForm(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _EventForm(),
    );
  }
}

class _EventList extends StatelessWidget {
  const _EventList({required this.events});

  final List<api.Event> events;

  @override
  Widget build(BuildContext context) {
    // Le serveur renvoie startsAt dans le fuseau famille : la date locale = substring(0, 10).
    final byDay = <String, List<api.Event>>{};
    for (final e in events) {
      (byDay[e.startsAt.substring(0, 10)] ??= []).add(e);
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
      children: [
        for (final entry in byDay.entries) ...[
          Padding(
            padding: const EdgeInsets.only(top: 12, bottom: 6),
            child: Text(
              _frenchDay(entry.key),
              style: AppTypography.textTheme.titleMedium,
            ),
          ),
          for (final e in entry.value) _EventTile(event: e),
        ],
      ],
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
    return '${days[d.weekday - 1]} ${d.day} ${months[d.month]}';
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

    return AppCard(
      child: Row(
        children: [
          CategoryPill(category: event.category),
          const SizedBox(width: 10),
          SizedBox(
            width: 44,
            child: Text(
              time,
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
            ),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  event.title,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                if (event.location != null && event.location!.isNotEmpty)
                  Text(
                    event.location!,
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.muted,
                    ),
                  ),
              ],
            ),
          ),
          if (event.isRecurring)
            const Icon(Icons.repeat, size: 16, color: AppColors.muted),
        ],
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
    try {
      await ref
          .read(agendaRepositoryProvider)
          .createEvent(
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
