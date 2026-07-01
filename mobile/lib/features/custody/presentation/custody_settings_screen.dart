import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../../family/application/family_context.dart';
import '../../members/data/members_repository.dart';
import '../data/custody_repository.dart';

/// US-CUST-1/2/9 + US-SET-3 — Paramètres › Garde : builder de rythme (dont
/// `custom` 14 nuits), jour/heure d'échange, parents A/B ; import des vacances
/// officielles. Réservé au droit `custody:edit`.
class CustodySettingsScreen extends ConsumerStatefulWidget {
  const CustodySettingsScreen({super.key});

  @override
  ConsumerState<CustodySettingsScreen> createState() =>
      _CustodySettingsScreenState();
}

class _CustodySettingsScreenState extends ConsumerState<CustodySettingsScreen> {
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

  void _prefill(api.CustodyPattern? pattern, List<api.Member> parents) {
    if (_initialized) return;
    // Défaut d'un premier rythme : aujourd'hui (jamais une date d'application codée en dur).
    final now = DateTime.now();
    _anchor = DateTime(now.year, now.month, now.day);
    if (pattern != null) {
      _kind = pattern.kind;
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
    }
    // Défauts : deux premiers parents distincts.
    _parentA ??= parents.isNotEmpty ? parents.first.id : null;
    _parentB ??= parents.length > 1 ? parents[1].id : null;
    _initialized = true;
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
    setState(() {
      _saving = true;
      _error = null;
    });
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    final l = AppL10n.of(context);
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
          );
      ref.invalidate(familyCustodyPatternProvider);
      ref.invalidate(familyCustodyDaysProvider);
      ref.invalidate(familyCustodyStatsProvider);
      if (navigator.canPop()) navigator.pop();
      messenger.showSnackBar(SnackBar(content: Text(l.custodySaved)));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
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
          child: ErrorBanner(e is ApiException ? e.message : e.toString()),
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
            DropdownMenuItem(value: 'custom', child: Text(l.custodyKindCustom)),
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
      ],
    );
  }

  Widget _customChip(int i) {
    final slot = _custom[i];
    final isA = slot == 'A';
    return InkWell(
      key: ValueKey('custody-custom-$i'),
      borderRadius: BorderRadius.circular(8),
      onTap: () => setState(() => _custom[i] = isA ? 'B' : 'A'),
      child: Container(
        width: 36,
        height: 36,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: isA ? AppColors.accentSoft2 : AppColors.positiveSoft,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(
            color: isA ? AppColors.accent : AppColors.positive,
          ),
        ),
        child: Text(
          slot,
          style: TextStyle(
            fontWeight: FontWeight.w700,
            color: isA ? AppColors.accentStrong : AppColors.positiveStrong,
          ),
        ),
      ),
    );
  }
}
