import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/error/error_text.dart';
import '../../../core/push/push_controller.dart';
import '../../../core/push/push_gateway.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/util/date_labels.dart';
import '../../../core/util/detached_providers.dart';
import '../../../core/widgets/confirm_dialog.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../../members/data/members_repository.dart';
import '../data/notification_prefs_repository.dart';

/// Ouvre les réglages de notification de l'app dans l'OS : une permission
/// refusée ne peut plus être rendue que là (iOS ne repose jamais la question).
/// Canal natif (`MainActivity.kt`, `AppDelegate.swift`) plutôt qu'un plugin :
/// ceux du marché n'offrent plus CocoaPods, dont dépend le build iOS.
final openNotificationSettingsProvider = Provider<Future<void> Function()>(
  (ref) => () async {
    try {
      await const MethodChannel(
        'coparentplanner/system_settings',
      ).invokeMethod<void>('openNotificationSettings');
    } on PlatformException catch (_) {
      // Plateforme sans réglage atteignable : le texte explicatif reste affiché.
    } on MissingPluginException catch (_) {}
  },
);

/// US-SET-1 (Lot 7) + US-PUSH-4/6 (Lot 11) — écran « Paramètres ›
/// Notifications ».
///
/// - **Push** (ce téléphone) : un switch par catégorie, désactivé par défaut.
///   Activer le premier demande la permission système — c'est le « geste de
///   valeur » après lequel l'invite a du sens. Permission refusée : les
///   switchs se figent et un bouton mène aux réglages du téléphone.
/// - **E-mail** : un switch par catégorie, activé par défaut.
/// - **Heures silencieuses**, communes aux deux canaux.
///
/// **Chaque switch s'enregistre dès qu'il change**, comme dans les réglages du
/// téléphone. Un bouton « Enregistrer » commun laissait croire le contraire :
/// l'invite système s'affichait au geste, l'appareil s'enregistrait, et la
/// préférence restait pourtant perdue faute d'appui sur le bouton en bas de
/// page (constaté en recette le 2026-09-23). Seule la plage de silence, qui se
/// saisit en deux temps (début puis fin), garde un bouton — le sien.
/// - **Appareils** : ceux qui reçoivent les push, révocables à distance.
///
/// `invitation` n'a pas de switch push : aucun déclencheur push ne la porte
/// (une invitation s'adresse à une adresse e-mail, pas à un compte). Les
/// dépenses n'apparaissent que si le module est actif et visible. La
/// validation (quiet-hours, catégories) reste côté serveur : un 422 s'affiche
/// dans la bannière d'erreur.
class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() =>
      _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen> {
  /// Catégories notifiables par e-mail (ordre d'affichage), hors dépenses.
  static const List<String> _emailCategories = [
    'custody',
    'agenda',
    'exchanges',
    'invitation',
  ];

  /// Catégories notifiables par push (ordre d'affichage), hors dépenses.
  static const List<String> _pushCategories = [
    'custody',
    'agenda',
    'exchanges',
  ];

  final Map<String, bool> _email = {};
  final Map<String, bool> _push = {};
  String? _quietStart;
  String? _quietEnd;

  /// Plage connue du serveur : c'est elle que renvoie l'enregistrement d'un
  /// switch, pour qu'une plage en cours de saisie ne parte pas avec lui.
  String? _savedQuietStart;
  String? _savedQuietEnd;
  String _locale = 'fr-FR';
  bool _seeded = false;
  bool _savingQuiet = false;
  String? _error;

  /// Enregistrements en file : un PUT complet à la fois, dans l'ordre des
  /// gestes — deux switchs basculés vite ne s'écrasent pas l'un l'autre.
  Future<void> _queue = Future.value();

  bool get _quietDirty =>
      _quietStart != _savedQuietStart || _quietEnd != _savedQuietEnd;

  void _seed(api.NotificationPrefs prefs) {
    if (_seeded) return;
    final email = prefs.email ?? const {};
    for (final cat in [..._emailCategories, 'expenses']) {
      _email[cat] = email[cat] ?? true;
    }
    // Toutes les catégories connues du serveur sont renvoyées telles quelles :
    // celles que l'écran ne montre pas ne doivent pas changer à l'enregistrement.
    _push.addAll(prefs.push ?? const {});
    for (final cat in [..._pushCategories, 'expenses']) {
      _push[cat] = _push[cat] ?? false;
    }
    _quietStart = _savedQuietStart = prefs.quietHoursStart;
    _quietEnd = _savedQuietEnd = prefs.quietHoursEnd;
    _locale = prefs.locale;
    _seeded = true;
  }

  String _categoryLabel(AppL10n l, String cat) => switch (cat) {
    'custody' => l.notifCategoryCustody,
    'agenda' => l.notifCategoryAgenda,
    'exchanges' => l.notifCategoryExchanges,
    'invitation' => l.notifCategoryInvitation,
    'expenses' => l.notifCategoryExpenses,
    _ => cat,
  };

  /// Switch push : activer le premier sujet demande la permission système ;
  /// refusée, le switch reste coupé et rien n'est enregistré.
  Future<void> _togglePush(String cat, bool value) async {
    if (value) {
      final controller = ref.read(pushControllerProvider.notifier);
      var permission = ref.read(pushControllerProvider).permission;
      if (permission == PushPermission.notDetermined) {
        permission = await controller.requestPermission();
      }
      if (!mounted || permission != PushPermission.granted) return;
    }
    await _toggle(_push, cat, value);
  }

  /// Bascule un switch et l'enregistre aussitôt ; en cas de refus, il revient
  /// à sa position et l'erreur s'affiche.
  Future<void> _toggle(Map<String, bool> channel, String cat, bool value) {
    final previous = channel[cat];
    setState(() {
      channel[cat] = value;
      _error = null;
    });
    return _enqueue(
      quietStart: () => _savedQuietStart,
      quietEnd: () => _savedQuietEnd,
      onFailure: () => channel[cat] = previous ?? !value,
    );
  }

  /// Enregistre la plage de silence saisie.
  Future<void> _saveQuiet() async {
    final l = AppL10n.of(context);
    setState(() {
      _savingQuiet = true;
      _error = null;
    });
    final start = _quietStart;
    final end = _quietEnd;
    final saved = await _enqueue(quietStart: () => start, quietEnd: () => end);
    if (!mounted) return;
    setState(() {
      _savingQuiet = false;
      if (saved) {
        _savedQuietStart = start;
        _savedQuietEnd = end;
      }
    });
    if (saved) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(l.notificationsQuietSaved)));
    }
  }

  /// PUT complet, sérialisé derrière les précédents. Les switchs sont lus au
  /// moment de l'envoi : un geste fait pendant qu'un autre part est inclus.
  Future<bool> _enqueue({
    required String? Function() quietStart,
    required String? Function() quietEnd,
    void Function()? onFailure,
  }) {
    // Conteneur détaché : l'écran peut être quitté pendant l'envoi (C23).
    final providers = context.providers;
    final done = _queue.then((_) async {
      try {
        await ref
            .read(notificationPrefsRepositoryProvider)
            .save(
              email: Map<String, bool>.from(_email),
              push: Map<String, bool>.from(_push),
              quietHoursStart: quietStart(),
              quietHoursEnd: quietEnd(),
              locale: _locale,
            );
        providers.invalidate(notificationPrefsProvider);
        return true;
      } on ApiException catch (e) {
        if (mounted) {
          setState(() {
            onFailure?.call();
            _error = errorText(context, e);
          });
        }
        return false;
      }
    });
    _queue = done;
    return done;
  }

  Future<void> _revoke(api.Device device) async {
    final l = AppL10n.of(context);
    final providers = context.providers;
    final confirmed = await confirmAction(
      context,
      title: l.notificationsDeviceRevokeTitle,
      body: l.notificationsDeviceRevokeBody,
      confirmLabel: l.notificationsDeviceRevoke,
      confirmKey: const ValueKey('notif-device-revoke-confirm'),
      destructive: true,
    );
    if (!confirmed || !mounted) return;
    try {
      await ref.read(deviceRegistrationProvider).revoke(device.id);
      providers.invalidate(pushDevicesProvider);
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(l.notificationsDeviceRevoked)));
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = errorText(context, e));
    }
  }

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

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final prefsAsync = ref.watch(notificationPrefsProvider);
    final permission = ref.watch(
      pushControllerProvider.select((s) => s.permission),
    );
    final expenses = ref.watch(canViewProvider('expenses'));
    final pushEditable =
        permission == PushPermission.granted ||
        permission == PushPermission.notDetermined;

    return Scaffold(
      appBar: AppBar(title: Text(l.notificationsTitle)),
      body: prefsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Padding(
          padding: const EdgeInsets.all(20),
          child: ErrorBanner(errorText(context, e)),
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
                l.notificationsPushSection.toUpperCase(),
                style: AppTypography.eyebrow,
              ),
              const SizedBox(height: 8),
              AppCard(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _PushStatus(
                      permission: permission,
                      onOpenSettings: () =>
                          ref.read(openNotificationSettingsProvider)(),
                    ),
                    if (permission != PushPermission.unavailable)
                      for (final cat in [
                        ..._pushCategories,
                        if (expenses) 'expenses',
                      ])
                        SwitchListTile(
                          key: ValueKey('notif-push-$cat'),
                          title: Text(_categoryLabel(l, cat)),
                          value:
                              permission != PushPermission.denied &&
                              (_push[cat] ?? false),
                          onChanged: pushEditable
                              ? (v) => _togglePush(cat, v)
                              : null,
                        ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              Text(
                l.notificationsEmailSection.toUpperCase(),
                style: AppTypography.eyebrow,
              ),
              const SizedBox(height: 8),
              AppCard(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                child: Column(
                  children: [
                    for (final cat in [
                      ..._emailCategories,
                      if (expenses) 'expenses',
                    ])
                      SwitchListTile(
                        key: ValueKey('notif-switch-$cat'),
                        title: Text(_categoryLabel(l, cat)),
                        value: _email[cat] ?? true,
                        onChanged: (v) => _toggle(_email, cat, v),
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
                      enabled: !_savingQuiet,
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
                      enabled: !_savingQuiet,
                      onTap: () => _pick(start: false),
                      onClear: _quietEnd == null
                          ? null
                          : () => setState(() => _quietEnd = null),
                    ),
                    const SizedBox(height: 16),
                    // Actif seulement s'il y a quelque chose à enregistrer :
                    // un bouton toujours allumé laissait croire que les
                    // switchs, eux aussi, attendaient ce geste.
                    FilledButton(
                      key: const ValueKey('notif-save'),
                      onPressed: _savingQuiet || !_quietDirty
                          ? null
                          : _saveQuiet,
                      child: _savingQuiet
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Text(l.notificationsQuietSave),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 28),
              _DevicesSection(onRevoke: _revoke),
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

/// En-tête de la section push : ce que le téléphone permet, et quoi faire.
class _PushStatus extends StatelessWidget {
  const _PushStatus({required this.permission, required this.onOpenSettings});

  final PushPermission permission;
  final VoidCallback onOpenSettings;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final text = switch (permission) {
      PushPermission.granted => l.notificationsPushHint,
      PushPermission.notDetermined => l.notificationsPushAsk,
      PushPermission.denied => l.notificationsPushDenied,
      PushPermission.unavailable => l.notificationsPushUnavailable,
    };
    return Padding(
      padding: const EdgeInsets.fromLTRB(8, 12, 8, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            text,
            key: ValueKey('notif-push-status-${permission.name}'),
            style: const TextStyle(fontSize: 13, color: AppColors.muted),
          ),
          if (permission == PushPermission.denied) ...[
            const SizedBox(height: 8),
            OutlinedButton(
              key: const ValueKey('notif-push-settings'),
              onPressed: onOpenSettings,
              child: Text(l.notificationsPushOpenSettings),
            ),
          ],
        ],
      ),
    );
  }
}

/// Appareils recevant les push (US-PUSH-3). Celui-ci n'est pas révocable
/// d'ici : il se réenregistrerait à la prochaine ouverture ; pour le faire
/// taire, on coupe ses sujets ou on se déconnecte.
class _DevicesSection extends ConsumerWidget {
  const _DevicesSection({required this.onRevoke});

  final Future<void> Function(api.Device device) onRevoke;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final devices = ref.watch(pushDevicesProvider);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          l.notificationsDevicesSection.toUpperCase(),
          style: AppTypography.eyebrow,
        ),
        const SizedBox(height: 8),
        AppCard(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
          child: devices.when(
            loading: () => const Padding(
              padding: EdgeInsets.all(12),
              child: Center(child: CircularProgressIndicator()),
            ),
            error: (e, _) => Padding(
              padding: const EdgeInsets.all(8),
              child: ErrorBanner(errorText(context, e)),
            ),
            data: (list) => Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(8, 4, 8, 4),
                  child: Text(
                    list.devices.isEmpty
                        ? l.notificationsDevicesEmpty
                        : l.notificationsDevicesHint,
                    style: const TextStyle(
                      fontSize: 13,
                      color: AppColors.muted,
                    ),
                  ),
                ),
                for (final device in list.devices)
                  _DeviceTile(
                    device: device,
                    isCurrent: device.id == list.currentDeviceId,
                    onRevoke: () => onRevoke(device),
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _DeviceTile extends StatelessWidget {
  const _DeviceTile({
    required this.device,
    required this.isCurrent,
    required this.onRevoke,
  });

  final api.Device device;
  final bool isCurrent;
  final VoidCallback onRevoke;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final model = device.deviceModel;
    final name = model != null && model.isNotEmpty
        ? model
        : (device.platform == 'ios'
              ? l.notificationsDeviceIos
              : l.notificationsDeviceAndroid);
    final seen = DateTime.tryParse(device.lastSeenAt ?? '');
    final subtitle = [
      if (isCurrent) l.notificationsDeviceThis,
      if (seen != null)
        l.notificationsDeviceLastSeen(
          DateLabels.longDate(l.localeName, seen.toLocal()),
        ),
    ].join(' · ');
    return ListTile(
      key: ValueKey('notif-device-${device.id}'),
      leading: Icon(
        device.platform == 'ios' ? Icons.phone_iphone : Icons.phone_android,
        color: isCurrent ? AppColors.accent : AppColors.muted,
      ),
      title: Text(name),
      subtitle: subtitle.isEmpty ? null : Text(subtitle),
      trailing: isCurrent
          ? null
          : TextButton(
              key: ValueKey('notif-device-revoke-${device.id}'),
              onPressed: onRevoke,
              child: Text(l.notificationsDeviceRevoke),
            ),
    );
  }
}
