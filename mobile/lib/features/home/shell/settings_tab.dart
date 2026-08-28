import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/error/error_text.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../core/widgets/initial_avatar.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/application/auth_controller.dart';
import '../../auth/application/auth_state.dart';
import '../../family/application/family_context.dart';
import '../../family/presentation/family_switcher.dart';
import '../../members/presentation/members_screen.dart';
import '../../notifications/presentation/notifications_screen.dart';
import '../../settings/presentation/privacy_screen.dart';

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
      if (mounted) setState(() => _error = errorText(context, e));
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
          // mob-param U-04 : carte de profil (en-tête Compte).
          if (auth is AuthAuthenticated) ...[
            _ProfileCard(user: auth.user),
            const SizedBox(height: 12),
          ],
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
                  const SizedBox(height: 8),
                  TextButton.icon(
                    key: const ValueKey('settings-switch-family'),
                    onPressed: () => showFamilySwitcher(context, ref),
                    icon: const Icon(Icons.swap_horiz),
                    label: Text(l.settingsSwitchFamily),
                  ),
                ],
              ),
            ),
          const SizedBox(height: 12),
          if (family != null)
            ListTile(
              key: const ValueKey('settings-members'),
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.group_outlined),
              title: Text(l.settingsMembers),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute<void>(builder: (_) => const MembersScreen()),
              ),
            ),
          ListTile(
            key: const ValueKey('settings-notifications'),
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.notifications_none),
            title: Text(l.notificationsTitle),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => const NotificationsScreen(),
              ),
            ),
          ),
          ListTile(
            key: const ValueKey('settings-privacy'),
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.privacy_tip_outlined),
            title: Text(l.privacyTitle),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute<void>(builder: (_) => const PrivacyScreen()),
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
            // Était écrit « Francais (fr-FR) » en dur, sans cédille et hors du
            // catalogue : la seule chaîne visible de l'app qui ne pouvait pas être
            // traduite (C62). Chaque catalogue porte désormais son propre nom de
            // langue, dans cette langue.
            trailing: Text(l.settingsLanguageValue),
          ),
          const Divider(),
          const SizedBox(height: 8),
          // mob-param U-06 : « Se déconnecter » terracotta sans icône.
          OutlinedButton(
            key: const ValueKey('settings-logout'),
            style: OutlinedButton.styleFrom(
              foregroundColor: const Color(0xFFB85C36),
              backgroundColor: AppColors.card,
              side: const BorderSide(color: Color(0xFFEAD3C7)),
              minimumSize: const Size.fromHeight(50),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(13),
              ),
            ),
            onPressed: () => ref.read(authControllerProvider.notifier).logout(),
            child: Text(l.settingsLogout),
          ),
        ],
      ),
    );
  }
}

/// mob-param U-04 : carte de profil (avatar + nom + e-mail).
class _ProfileCard extends StatelessWidget {
  const _ProfileCard({required this.user});

  final UserProfile user;

  @override
  Widget build(BuildContext context) {
    final name = (user.fullName?.isNotEmpty ?? false)
        ? user.fullName!
        : user.email;
    return AppCard(
      child: Row(
        children: [
          InitialAvatar(
            initial: InitialAvatar.initialOf(name),
            color: AppColors.accent,
            size: 56,
            fontSize: 24,
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(name, style: AppTypography.textTheme.titleLarge),
                const SizedBox(height: 2),
                Text(
                  user.email,
                  style: const TextStyle(fontSize: 13, color: AppColors.muted),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
