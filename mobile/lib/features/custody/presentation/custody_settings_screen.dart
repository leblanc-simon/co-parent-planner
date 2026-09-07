import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/error/error_text.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/util/detached_providers.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../../family/application/family_context.dart';
import '../../members/data/members_repository.dart';
import '../application/custody_palette.dart';
import '../data/custody_repository.dart';

/// US-CUST-1/2/9 + US-SET-3 — Paramètres › Garde : builder de rythme (dont
/// `custom` 14 nuits et `midweek_weekends`), jour/heure d'échange, parents A/B ;
/// import des vacances officielles. Réservé au droit `custody:edit`.
///
/// Le modèle `midweek_weekends` manquait au sélecteur alors que le serveur le connaît
/// et que le web permet de le choisir : une famille ainsi configurée voyait un champ
/// « Modèle » vide, et toute sauvegarde partait sans le bloc `settings` que le serveur
/// exige pour ce modèle — 422 systématique (revue mobile 2026-08, constat C26).
class CustodySettingsScreen extends ConsumerStatefulWidget {
  const CustodySettingsScreen({super.key});

  @override
  ConsumerState<CustodySettingsScreen> createState() =>
      _CustodySettingsScreenState();
}

class _CustodySettingsScreenState extends ConsumerState<CustodySettingsScreen> {
  /// Modèles éditables par cet écran — miroir de `CustodyPatternKind` côté serveur.
  static const _kinds = {
    'alternating',
    'pattern_2_2_3',
    'midweek_weekends',
    'custom',
  };

  bool _initialized = false;
  bool _saving = false;
  String? _error;

  String _kind = 'alternating';
  DateTime _anchor = DateTime(2026);
  int _weekday = 0; // 0 = dimanche (convention serveur)
  TimeOfDay _time = const TimeOfDay(hour: 19, minute: 0);
  String? _parentA;
  String? _parentB;
  List<String> _custom = List.filled(14, 'A');

  /// Réglages de `midweek_weekends`, dans la convention serveur (0 = dimanche) :
  /// jours de week-end alternés, et parent fixe de chaque jour de semaine restant.
  /// Défauts alignés sur le formulaire web (`settings.html.twig`).
  Set<int> _weekendDays = {6, 0};
  Map<int, String> _weekdayParents = {1: 'A', 2: 'A', 3: 'B', 4: 'B', 5: 'B'};
  String _weekendStart = 'A';

  void _prefill(api.CustodyPattern? pattern, List<api.Member> parents) {
    if (_initialized) return;
    // Défaut d'un premier rythme : aujourd'hui (jamais une date d'application codée en dur).
    final now = DateTime.now();
    _anchor = DateTime(now.year, now.month, now.day);
    if (pattern != null) {
      // Jamais une valeur absente des items du sélecteur : `DropdownButtonFormField`
      // lève alors une assertion en debug et affiche un champ vide en release. C'est
      // ce qui arrivait à `midweek_weekends` ; le repli protège des modèles futurs.
      _kind = _kinds.contains(pattern.kind) ? pattern.kind : 'alternating';
      if (pattern.anchorDate != null) {
        _anchor = DateTime.parse(pattern.anchorDate!);
      }
      _weekday = pattern.handoverWeekday;
      final parts = pattern.handoverTime.split(':');
      if (parts.length >= 2) {
        _time = TimeOfDay(
          hour: int.tryParse(parts[0]) ?? 19,
          minute: int.tryParse(parts[1]) ?? 0,
        );
      }
      _parentA = pattern.parentAMembershipId;
      _parentB = pattern.parentBMembershipId;
      if (pattern.customPattern != null &&
          pattern.customPattern!.length == 14) {
        _custom = List.of(pattern.customPattern!);
      }
      _prefillMidweek(pattern.settings);
    }
    // Défauts : deux premiers parents distincts.
    _parentA ??= parents.isNotEmpty ? parents.first.id : null;
    _parentB ??= parents.length > 1 ? parents[1].id : null;
    _initialized = true;
  }

  /// Relit les `settings` du serveur, typés `dynamic` au contrat (objet libre dont
  /// la forme est validée côté serveur). Toute valeur inattendue laisse simplement
  /// les défauts en place : un rythme mal formé ne doit pas rendre l'écran inutilisable.
  void _prefillMidweek(Object? settings) {
    if (settings is! Map) return;
    final weekend = settings['weekend_days'];
    if (weekend is List) {
      final parsed = <int>{
        for (final d in weekend)
          if (int.tryParse('$d') case final v?)
            if (v >= 0 && v <= 6) v,
      };
      if (parsed.isNotEmpty && parsed.length <= 3) _weekendDays = parsed;
    }
    final parents = settings['weekday_parents'];
    if (parents is Map) {
      final parsed = <int, String>{
        for (final entry in parents.entries)
          if (int.tryParse('${entry.key}') case final day?)
            if (entry.value == 'A' || entry.value == 'B') day: '${entry.value}',
      };
      if (parsed.isNotEmpty) _weekdayParents = parsed;
    }
    final start = settings['weekend_start_parent'];
    if (start == 'A' || start == 'B') _weekendStart = '$start';
  }

  /// Bloc `settings` du contrat, reconstruit à l'enregistrement.
  ///
  /// Les jours de semaine non week-end doivent être couverts **exactement** (ni
  /// manquant, ni en trop) : le serveur refuse sinon en `custody.error.settings_invalid`.
  /// On les dérive donc des jours de week-end plutôt que de recopier la carte éditée.
  Map<String, Object?> _midweekSettings() {
    final weekend = _weekendDays.toList()..sort();
    return {
      'weekend_days': weekend,
      'weekday_parents': {
        for (var d = 0; d <= 6; d++)
          if (!_weekendDays.contains(d)) '$d': _weekdayParents[d] ?? 'A',
      },
      'weekend_start_parent': _weekendStart,
    };
  }

  String _fmtDate(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  String _fmtTime(TimeOfDay t) =>
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  Future<void> _save() async {
    final family = ref.read(familyContextProvider);
    if (family == null) return;
    // Deux parents distincts requis (famille avec un seul parent, ou même choix des deux côtés).
    if (_parentA == null || _parentB == null || _parentA == _parentB) {
      setState(() => _error = AppL10n.of(context).custodyParentsRequired);
      return;
    }
    // Contrainte serveur reproduite ici : un à trois jours de week-end. La signaler
    // avant l'envoi vaut mieux qu'un 422 « settings_invalid » sans repère à l'écran.
    if (_kind == 'midweek_weekends' &&
        (_weekendDays.isEmpty || _weekendDays.length > 3)) {
      setState(() => _error = AppL10n.of(context).custodyMidweekInvalid);
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    final l = AppL10n.of(context);
    // Conteneur détaché : le rythme est enregistré, les jours régénérés côté
    // serveur ; le cache doit suivre même si l'écran est quitté pendant l'envoi
    // (constat C23).
    final providers = context.providers;
    try {
      await ref
          .read(custodyRepositoryProvider)
          .updatePattern(
            family.familyId,
            kind: _kind,
            anchorDate: _fmtDate(_anchor),
            handoverWeekday: _weekday,
            handoverTime: _fmtTime(_time),
            parentAMembershipId: _parentA!,
            parentBMembershipId: _parentB!,
            customPattern: _kind == 'custom' ? _custom : null,
            // `settings` n'est accepté QUE pour ce modèle : l'envoyer ailleurs vaut
            // un `custody.error.config_conflict`.
            settings: _kind == 'midweek_weekends' ? _midweekSettings() : null,
          );
      providers
        ..invalidate(familyCustodyPatternProvider)
        ..invalidate(familyCustodyDaysProvider)
        ..invalidate(familyCustodyStatsProvider);
      if (navigator.canPop()) navigator.pop();
      messenger.showSnackBar(SnackBar(content: Text(l.custodySaved)));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = errorText(context, e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final patternAsync = ref.watch(familyCustodyPatternProvider);
    final membersAsync = ref.watch(familyMembersProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l.custodySettingsTitle)),
      body: membersAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Padding(
          padding: const EdgeInsets.all(20),
          child: ErrorBanner(errorText(context, e)),
        ),
        data: (members) {
          final parents = members
              .where((m) => m.role == 'parent')
              .toList(growable: false);
          _prefill(patternAsync.valueOrNull, parents);

          return _form(l, parents);
        },
      ),
    );
  }

  Widget _form(AppL10n l, List<api.Member> parents) {
    // Le rythme demande `custody:edit` ; le CHOIX DES COULEURS, non (cdc §8.2) — c'est une
    // préférence personnelle d'affichage, offerte à quiconque voit le planning, un beau-parent en
    // lecture compris. L'écran porte donc les deux sections sous deux droits différents, exactement
    // comme l'onglet « Planning de garde » du web.
    final canEdit = ref.watch(custodyCanEditProvider);
    final weekdays = <int, String>{
      0: l.weekdaySunday,
      1: l.weekdayMonday,
      2: l.weekdayTuesday,
      3: l.weekdayWednesday,
      4: l.weekdayThursday,
      5: l.weekdayFriday,
      6: l.weekdaySaturday,
    };

    String parentLabel(api.Member m) =>
        (m.fullName?.isNotEmpty ?? false) ? m.fullName! : (m.email ?? m.id);

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        if (_error != null) ...[
          ErrorBanner(_error!),
          const SizedBox(height: 12),
        ],
        if (canEdit) ...[
          DropdownButtonFormField<String>(
            key: const ValueKey('custody-kind'),
            initialValue: _kind,
            decoration: InputDecoration(labelText: l.custodyKind),
            items: [
              DropdownMenuItem(
                value: 'alternating',
                child: Text(l.custodyKindAlternating),
              ),
              DropdownMenuItem(
                value: 'pattern_2_2_3',
                child: Text(l.custodyKind223),
              ),
              DropdownMenuItem(
                value: 'midweek_weekends',
                child: Text(l.custodyKindMidweek),
              ),
              DropdownMenuItem(
                value: 'custom',
                child: Text(l.custodyKindCustom),
              ),
            ],
            onChanged: (v) => setState(() => _kind = v ?? _kind),
          ),
          const SizedBox(height: 12),
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(l.custodyAnchorDate),
            subtitle: Text(_fmtDate(_anchor)),
            trailing: const Icon(Icons.calendar_today, size: 18),
            onTap: () async {
              final picked = await showDatePicker(
                context: context,
                initialDate: _anchor,
                firstDate: DateTime(2020),
                lastDate: DateTime(2035),
              );
              if (picked != null) setState(() => _anchor = picked);
            },
          ),
          DropdownButtonFormField<int>(
            key: const ValueKey('custody-weekday'),
            initialValue: _weekday,
            decoration: InputDecoration(labelText: l.custodyHandoverWeekday),
            items: [
              for (final e in weekdays.entries)
                DropdownMenuItem(value: e.key, child: Text(e.value)),
            ],
            onChanged: (v) => setState(() => _weekday = v ?? _weekday),
          ),
          const SizedBox(height: 12),
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(l.custodyHandoverTime),
            subtitle: Text(_fmtTime(_time)),
            trailing: const Icon(Icons.schedule, size: 18),
            onTap: () async {
              final picked = await showTimePicker(
                context: context,
                initialTime: _time,
              );
              if (picked != null) setState(() => _time = picked);
            },
          ),
          DropdownButtonFormField<String>(
            key: const ValueKey('custody-parent-a'),
            initialValue: _parentA,
            decoration: InputDecoration(labelText: l.custodyParentA),
            items: [
              for (final m in parents)
                DropdownMenuItem(value: m.id, child: Text(parentLabel(m))),
            ],
            onChanged: (v) => setState(() => _parentA = v),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            key: const ValueKey('custody-parent-b'),
            initialValue: _parentB,
            decoration: InputDecoration(labelText: l.custodyParentB),
            items: [
              for (final m in parents)
                DropdownMenuItem(value: m.id, child: Text(parentLabel(m))),
            ],
            onChanged: (v) => setState(() => _parentB = v),
          ),
          if (_kind == 'custom') ...[
            const SizedBox(height: 16),
            Text(l.custodyCustomPattern, style: AppTypography.eyebrow),
            const SizedBox(height: 8),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [for (var i = 0; i < 14; i++) _customChip(i)],
            ),
          ],
          if (_kind == 'midweek_weekends') ...[
            const SizedBox(height: 16),
            Text(l.custodyMidweekTitle, style: AppTypography.eyebrow),
            const SizedBox(height: 4),
            Text(
              l.custodyMidweekHint,
              style: const TextStyle(fontSize: 12.5, color: AppColors.muted),
            ),
            const SizedBox(height: 8),
            // Semaine affichée lundi → dimanche comme le calendrier, alors que la
            // convention serveur numérote à partir du dimanche.
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final d in [1, 2, 3, 4, 5, 6, 0]) _midweekChip(l, d),
              ],
            ),
            const SizedBox(height: 12),
            Text(l.custodyWeekendStart, style: AppTypography.eyebrow),
            const SizedBox(height: 6),
            SegmentedButton<String>(
              key: const ValueKey('custody-weekend-start'),
              segments: [
                ButtonSegment(
                  value: 'A',
                  label: Text(_parentName(parents, _parentA, l.custodyParentA)),
                ),
                ButtonSegment(
                  value: 'B',
                  label: Text(_parentName(parents, _parentB, l.custodyParentB)),
                ),
              ],
              selected: {_weekendStart},
              onSelectionChanged: (v) =>
                  setState(() => _weekendStart = v.first),
            ),
          ],
          const SizedBox(height: 24),
          FilledButton(
            key: const ValueKey('custody-save'),
            onPressed: _saving ? null : _save,
            child: Text(l.custodySave),
          ),
          if (_saving) ...[
            const SizedBox(height: 8),
            Text(
              l.custodyPatternSaving,
              style: const TextStyle(color: AppColors.muted),
            ),
          ],
          const SizedBox(height: 28),
        ],
        // Sans droit d'édition NI rythme configuré, la section des couleurs n'a aucun parent à
        // colorer : l'écran serait entièrement blanc. On dit ce qui manque plutôt que rien.
        if (!canEdit &&
            ref.watch(familyCustodyPatternProvider).valueOrNull == null)
          Text(
            l.custodyNoPattern,
            style: const TextStyle(color: AppColors.muted),
          ),
        _CustodyColorsSection(parents: parents),
      ],
    );
  }

  /// Nom d'un parent choisi, ou son libellé générique tant qu'il n'est pas résolu.
  String _parentName(List<api.Member> parents, String? id, String fallback) {
    for (final m in parents) {
      if (m.id == id) {
        return (m.fullName?.isNotEmpty ?? false)
            ? m.fullName!
            : (m.email ?? fallback);
      }
    }

    return fallback;
  }

  /// Un jour de la semaine dans le rythme « milieu de semaine + week-ends » : il
  /// cycle parent A → parent B → week-end alterné, comme sur le web.
  Widget _midweekChip(AppL10n l, int day) {
    final labels = <int, String>{
      0: l.weekdaySunday,
      1: l.weekdayMonday,
      2: l.weekdayTuesday,
      3: l.weekdayWednesday,
      4: l.weekdayThursday,
      5: l.weekdayFriday,
      6: l.weekdaySaturday,
    };
    final isWeekend = _weekendDays.contains(day);
    final slot = isWeekend ? null : (_weekdayParents[day] ?? 'A');
    // Le builder de rythme montre les deux mêmes parents que le calendrier : il suit la palette du
    // spectateur (cdc §8.2), sans quoi la légende et les nuits se contrediraient à l'écran.
    final palette = ref.watch(custodyPaletteProvider);
    final (fg, bg, border) = slot == null
        ? (AppColors.ink2, AppColors.card, AppColors.border2)
        : (
            palette.forSlot(slot).ink,
            palette.forSlot(slot).soft,
            palette.forSlot(slot).border,
          );

    return InkWell(
      key: ValueKey('custody-mw-$day'),
      borderRadius: BorderRadius.circular(10),
      onTap: () => setState(() {
        // A → B → week-end → A
        if (isWeekend) {
          _weekendDays.remove(day);
          _weekdayParents[day] = 'A';
        } else if (_weekdayParents[day] == 'B') {
          _weekendDays.add(day);
        } else {
          _weekdayParents[day] = 'B';
        }
      }),
      child: Container(
        width: 46,
        padding: const EdgeInsets.symmetric(vertical: 8),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: border),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              labels[day]!.substring(0, 3).toUpperCase(),
              style: const TextStyle(fontSize: 10, color: AppColors.muted),
            ),
            const SizedBox(height: 2),
            Text(
              slot ?? l.custodyWeekend.substring(0, 2).toUpperCase(),
              style: TextStyle(fontWeight: FontWeight.w700, color: fg),
            ),
          ],
        ),
      ),
    );
  }

  Widget _customChip(int i) {
    final slot = _custom[i];
    final isA = slot == 'A';
    final customTint = ref.watch(custodyPaletteProvider).forSlot(slot);
    return InkWell(
      key: ValueKey('custody-custom-$i'),
      borderRadius: BorderRadius.circular(8),
      onTap: () => setState(() => _custom[i] = isA ? 'B' : 'A'),
      child: Container(
        width: 36,
        height: 36,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: customTint.soft,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: customTint.border),
        ),
        child: Text(
          slot,
          style: TextStyle(fontWeight: FontWeight.w700, color: customTint.ink),
        ),
      ),
    );
  }
}

/// Choix des couleurs du planning (cdc §8.2, évolution E5).
///
/// Sous `custody ≥ view` : la préférence n'écrit rien de la famille et ne modifie l'écran de
/// personne d'autre. Elle est **clé sur le parent observé**, jamais sur le slot A/B — un changement
/// de rythme peut échanger les titulaires, et la couleur « de Sophie » sauterait alors sur Marc.
class _CustodyColorsSection extends ConsumerStatefulWidget {
  const _CustodyColorsSection({required this.parents});

  final List<api.Member> parents;

  @override
  ConsumerState<_CustodyColorsSection> createState() =>
      _CustodyColorsSectionState();
}

class _CustodyColorsSectionState extends ConsumerState<_CustodyColorsSection> {
  bool _busy = false;
  String? _error;

  Future<void> _choose(String targetMembershipId, String palette) async {
    final family = ref.read(familyContextProvider);
    if (family == null || _busy) return;
    final l = AppL10n.of(context);
    final messenger = ScaffoldMessenger.of(context);
    final providers = context.providers;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref
          .read(custodyRepositoryProvider)
          .setColor(
            family.familyId,
            targetMembershipId: targetMembershipId,
            palette: palette,
          );
      // Le serveur est l'autorité : on relit la préférence enregistrée plutôt que de peindre
      // localement une teinte qu'il aurait pu refuser (deux parents de la même couleur).
      providers.invalidate(custodyColorsProvider);
      messenger.showSnackBar(SnackBar(content: Text(l.custodyColorsSaved)));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.localized(l));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final pattern = ref.watch(familyCustodyPatternProvider).valueOrNull;
    final palette = ref.watch(custodyPaletteProvider);
    final parentA = pattern?.parentAMembershipId;
    final parentB = pattern?.parentBMembershipId;
    // Sans rythme, aucun parent n'est désigné : il n'y a rien à colorer, et proposer un choix sans
    // cible mènerait droit à un 404.
    if (parentA == null || parentB == null) return const SizedBox.shrink();

    String nameOf(String id, String fallback) {
      for (final m in widget.parents) {
        if (m.id == id) {
          return (m.fullName?.isNotEmpty ?? false)
              ? m.fullName!
              : (m.email ?? fallback);
        }
      }

      return fallback;
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l.custodyColorsTitle, style: AppTypography.textTheme.titleMedium),
        const SizedBox(height: 4),
        Text(
          l.custodyColorsHint,
          style: const TextStyle(fontSize: 13, color: AppColors.muted),
        ),
        if (_error != null) ...[
          const SizedBox(height: 10),
          ErrorBanner(_error!),
        ],
        for (final (slot, id, fallback) in [
          ('A', parentA, l.custodyParentA),
          ('B', parentB, l.custodyParentB),
        ]) ...[
          const SizedBox(height: 14),
          Text(
            nameOf(id, fallback),
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 8),
          Semantics(
            label: l.custodyColorFor(nameOf(id, fallback)),
            container: true,
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final tint in CustodyPalette.tints)
                  _TintChip(
                    key: ValueKey('custody-color-$slot-${tint.value}'),
                    tint: tint,
                    selected: palette.forSlot(slot).value == tint.value,
                    onTap: _busy ? null : () => _choose(id, tint.value),
                  ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

/// Pastille de teinte : elle reproduit l'aspect d'une case du calendrier et porte le NOM de la
/// couleur — une grille de huit carrés ne se choisit pas sans lui (cdc §8.1, même raison que le
/// marqueur des parents). La sélection est un anneau, pas un simple changement de teinte.
class _TintChip extends StatelessWidget {
  const _TintChip({
    super.key,
    required this.tint,
    required this.selected,
    required this.onTap,
  });

  final CustodyTint tint;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      selected: selected,
      button: true,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
          decoration: BoxDecoration(
            color: tint.soft,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: tint.border, width: selected ? 3 : 1),
          ),
          child: Text(
            tint.label,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: tint.ink,
            ),
          ),
        ),
      ),
    );
  }
}
