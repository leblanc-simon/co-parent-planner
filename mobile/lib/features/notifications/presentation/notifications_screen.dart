import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../data/notification_prefs_repository.dart';

/// US-SET-1 (Lot 7) — écran « Paramètres › Notifications ».
///
/// Un switch e-mail par catégorie v1 (`custody`, `agenda`, `exchanges`,
/// `invitation`) + une plage de quiet-hours (début/fin, effaçables). Le canal
/// `push` et la catégorie `expenses` (réservés v2) ne sont pas affichés. La
/// validation des quiet-hours (format `HH:MM`, plage) reste côté serveur : un
/// 422 s'affiche dans la bannière d'erreur.
class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() =>
      _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen> {
  /// Catégories notifiables du canal e-mail en v1 (ordre d'affichage).
  static const List<String> _categories = [
    'custody',
    'agenda',
    'exchanges',
    'invitation',
  ];

  final Map<String, bool> _email = {};
  String? _quietStart;
  String? _quietEnd;
  String _locale = 'fr-FR';
  bool _seeded = false;
  bool _saving = false;
  String? _error;

  void _seed(api.NotificationPrefs prefs) {
    if (_seeded) return;
    final email = prefs.email ?? const {};
    for (final cat in _categories) {
      _email[cat] = email[cat] ?? true;
    }
    _quietStart = prefs.quietHoursStart;
    _quietEnd = prefs.quietHoursEnd;
    _locale = prefs.locale;
    _seeded = true;
  }

  String _categoryLabel(AppL10n l, String cat) => switch (cat) {
    'custody' => l.notifCategoryCustody,
    'agenda' => l.notifCategoryAgenda,
    'exchanges' => l.notifCategoryExchanges,
    'invitation' => l.notifCategoryInvitation,
    _ => cat,
  };

  TimeOfDay? _parse(String? hhmm) {
    if (hhmm == null || hhmm.isEmpty) return null;
    final parts = hhmm.split(':');
    if (parts.length < 2) return null;
    final h = int.tryParse(parts[0]);
    final m = int.tryParse(parts[1]);
    if (h == null || m == null) return null;
    return TimeOfDay(hour: h, minute: m);
  }

  String _format(TimeOfDay t) =>
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  Future<void> _pick({required bool start}) async {
    final current = _parse(start ? _quietStart : _quietEnd);
    final picked = await showTimePicker(
      context: context,
      initialTime: current ?? const TimeOfDay(hour: 22, minute: 0),
    );
    if (picked == null) return;
    setState(() {
      if (start) {
        _quietStart = _format(picked);
      } else {
        _quietEnd = _format(picked);
      }
    });
  }

  Future<void> _save() async {
    final l = AppL10n.of(context);
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ref
          .read(notificationPrefsRepositoryProvider)
          .save(
            email: Map<String, bool>.from(_email),
            quietHoursStart: _quietStart,
            quietHoursEnd: _quietEnd,
            locale: _locale,
          );
      ref.invalidate(notificationPrefsProvider);
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(l.notificationsSaved)));
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final prefsAsync = ref.watch(notificationPrefsProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l.notificationsTitle)),
      body: prefsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Padding(
          padding: const EdgeInsets.all(20),
          child: ErrorBanner(e is ApiException ? e.message : e.toString()),
        ),
        data: (prefs) {
          _seed(prefs);
          return ListView(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
            children: [
              if (_error != null) ...[
                ErrorBanner(_error!),
                const SizedBox(height: 12),
              ],
              Text(
                l.notificationsEmailSection.toUpperCase(),
                style: AppTypography.eyebrow,
              ),
              const SizedBox(height: 8),
              AppCard(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                child: Column(
                  children: [
                    for (final cat in _categories)
                      SwitchListTile(
                        key: ValueKey('notif-switch-$cat'),
                        title: Text(_categoryLabel(l, cat)),
                        value: _email[cat] ?? true,
                        onChanged: _saving
                            ? null
                            : (v) => setState(() => _email[cat] = v),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              Text(
                l.notificationsQuietSection.toUpperCase(),
                style: AppTypography.eyebrow,
              ),
              const SizedBox(height: 8),
              AppCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l.notificationsQuietHint,
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.muted,
                      ),
                    ),
                    const SizedBox(height: 12),
                    _QuietRow(
                      keyPrefix: 'notif-quiet-start',
                      label: l.notificationsQuietStart,
                      value: _quietStart,
                      placeholder: l.notificationsQuietUnset,
                      enabled: !_saving,
                      onTap: () => _pick(start: true),
                      onClear: _quietStart == null
                          ? null
                          : () => setState(() => _quietStart = null),
                    ),
                    const Divider(height: 20),
                    _QuietRow(
                      keyPrefix: 'notif-quiet-end',
                      label: l.notificationsQuietEnd,
                      value: _quietEnd,
                      placeholder: l.notificationsQuietUnset,
                      enabled: !_saving,
                      onTap: () => _pick(start: false),
                      onClear: _quietEnd == null
                          ? null
                          : () => setState(() => _quietEnd = null),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              FilledButton(
                key: const ValueKey('notif-save'),
                onPressed: _saving ? null : _save,
                child: _saving
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text(l.actionSave),
              ),
            ],
          );
        },
      ),
    );
  }
}

/// Ligne d'une borne de quiet-hours : libellé, heure (ou placeholder) et, si
/// définie, un bouton d'effacement (les bornes sont facultatives).
class _QuietRow extends StatelessWidget {
  const _QuietRow({
    required this.keyPrefix,
    required this.label,
    required this.value,
    required this.placeholder,
    required this.enabled,
    required this.onTap,
    required this.onClear,
  });

  final String keyPrefix;
  final String label;
  final String? value;
  final String placeholder;
  final bool enabled;
  final VoidCallback onTap;
  final VoidCallback? onClear;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: const TextStyle(fontSize: 15, color: AppColors.ink),
          ),
        ),
        TextButton(
          key: ValueKey(keyPrefix),
          onPressed: enabled ? onTap : null,
          child: Text(
            value ?? placeholder,
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w600,
              color: value == null ? AppColors.muted : AppColors.accent,
            ),
          ),
        ),
        if (onClear != null)
          IconButton(
            key: ValueKey('$keyPrefix-clear'),
            tooltip: MaterialLocalizations.of(context).deleteButtonTooltip,
            onPressed: enabled ? onClear : null,
            icon: const Icon(Icons.close, size: 18, color: AppColors.muted),
          ),
      ],
    );
  }
}
