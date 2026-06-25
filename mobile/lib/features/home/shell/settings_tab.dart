import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/application/auth_controller.dart';
import '../../auth/application/auth_state.dart';
import '../../family/application/family_context.dart';

/// US-SET-1 (sous-ensemble Lot 0) : profil (nom/telephone), langue, famille
/// active, deconnexion.
class SettingsTab extends ConsumerStatefulWidget {
  const SettingsTab({super.key});

  @override
  ConsumerState<SettingsTab> createState() => _SettingsTabState();
}

class _SettingsTabState extends ConsumerState<SettingsTab> {
  final _fullName = TextEditingController();
  final _phone = TextEditingController();
  bool _initialized = false;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _fullName.dispose();
    _phone.dispose();
    super.dispose();
  }

  void _seed(UserProfile user) {
    if (_initialized) return;
    _fullName.text = user.fullName ?? '';
    _phone.text = user.phone ?? '';
    _initialized = true;
  }

  Future<void> _save() async {
    final l = AppL10n.of(context);
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ref
          .read(authControllerProvider.notifier)
          .updateProfile(
            fullName: _fullName.text.trim(),
            phone: _phone.text.trim(),
          );
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(l.settingsProfileUpdated)));
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
    final auth = ref.watch(authControllerProvider);
    final family = ref.watch(familyContextProvider);

    if (auth is AuthAuthenticated) _seed(auth.user);

    return Scaffold(
      appBar: AppBar(title: Text(l.settingsTitle)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
        children: [
          if (family != null)
            AppCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    l.settingsActiveFamily.toUpperCase(),
                    style: AppTypography.eyebrow,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    family.familyName,
                    style: AppTypography.textTheme.titleLarge,
                  ),
                ],
              ),
            ),
          const SizedBox(height: 16),
          Text(l.settingsProfile, style: AppTypography.textTheme.titleMedium),
          const SizedBox(height: 12),
          if (_error != null) ...[
            ErrorBanner(_error!),
            const SizedBox(height: 12),
          ],
          TextField(
            key: const ValueKey('settings-fullname'),
            controller: _fullName,
            enabled: !_saving,
            decoration: InputDecoration(labelText: l.settingsFullName),
          ),
          const SizedBox(height: 12),
          TextField(
            key: const ValueKey('settings-phone'),
            controller: _phone,
            keyboardType: TextInputType.phone,
            enabled: !_saving,
            decoration: InputDecoration(labelText: l.settingsPhone),
          ),
          const SizedBox(height: 12),
          FilledButton(
            key: const ValueKey('settings-save'),
            onPressed: _saving ? null : _save,
            child: _saving
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(l.actionSave),
          ),
          const SizedBox(height: 24),
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(l.settingsLanguage),
            trailing: const Text('Francais (fr-FR)'),
          ),
          const Divider(),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            key: const ValueKey('settings-logout'),
            onPressed: () => ref.read(authControllerProvider.notifier).logout(),
            icon: const Icon(Icons.logout),
            label: Text(l.settingsLogout),
          ),
        ],
      ),
    );
  }
}
