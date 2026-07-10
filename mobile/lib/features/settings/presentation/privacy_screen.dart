import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/application/auth_controller.dart';
import '../data/rgpd_repository.dart';

/// US-SET-4 / US-SET-4b (Lot 8) — « Paramètres › Confidentialité & données » :
/// export RGPD (asynchrone, lien signé temporaire) et suppression/anonymisation du compte.
class PrivacyScreen extends ConsumerStatefulWidget {
  const PrivacyScreen({super.key});

  @override
  ConsumerState<PrivacyScreen> createState() => _PrivacyScreenState();
}

class _PrivacyScreenState extends ConsumerState<PrivacyScreen> {
  api.MeExport? _export;
  bool _busy = false;
  String? _error;

  Future<void> _run(Future<void> Function() action) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _requestExport() => _run(() async {
    final op = await ref.read(rgpdRepositoryProvider).requestExport();
    if (mounted) setState(() => _export = op);
  });

  Future<void> _refreshExport() => _run(() async {
    final id = _export?.id;
    if (id == null) return;
    final op = await ref.read(rgpdRepositoryProvider).exportStatus(id);
    if (mounted) setState(() => _export = op);
  });

  Future<void> _deleteAccount() async {
    final l = AppL10n.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(l.privacyDeleteConfirmTitle),
        content: Text(l.privacyDeleteConfirmBody),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(l.privacyCancel),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(l.privacyDeleteConfirm),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    await _run(() async {
      await ref.read(rgpdRepositoryProvider).deleteAccount();
      // Compte anonymisé : la session n'est plus valide → déconnexion locale.
      await ref.read(authControllerProvider.notifier).forceLogout();
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final theme = Theme.of(context);
    final export = _export;

    return Scaffold(
      appBar: AppBar(title: Text(l.privacyTitle)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(
                _error!,
                style: TextStyle(color: theme.colorScheme.error),
              ),
            ),

          // ---- Export (US-SET-4) ----
          Text(l.privacyExportSection, style: theme.textTheme.titleMedium),
          const SizedBox(height: 4),
          Text(l.privacyExportHint, style: theme.textTheme.bodySmall),
          const SizedBox(height: 12),
          FilledButton(
            key: const ValueKey('rgpd-export'),
            onPressed: _busy ? null : _requestExport,
            child: Text(l.privacyExportButton),
          ),
          if (export != null && export.status != 'done')
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Row(
                children: [
                  Expanded(child: Text(l.privacyExportPending)),
                  TextButton(
                    key: const ValueKey('rgpd-export-refresh'),
                    onPressed: _busy ? null : _refreshExport,
                    child: Text(l.privacyExportRefresh),
                  ),
                ],
              ),
            ),
          if (export != null &&
              export.status == 'done' &&
              export.downloadUrl != null)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(l.privacyExportReady, style: theme.textTheme.bodySmall),
                  const SizedBox(height: 4),
                  SelectableText(
                    export.downloadUrl!,
                    key: const ValueKey('rgpd-export-link'),
                  ),
                ],
              ),
            ),

          const Divider(height: 40),

          // ---- Suppression du compte (US-SET-4b) ----
          Text(l.privacyDeleteSection, style: theme.textTheme.titleMedium),
          const SizedBox(height: 4),
          Text(l.privacyDeleteHint, style: theme.textTheme.bodySmall),
          const SizedBox(height: 12),
          FilledButton(
            key: const ValueKey('rgpd-delete'),
            style: FilledButton.styleFrom(
              backgroundColor: theme.colorScheme.error,
            ),
            onPressed: _busy ? null : _deleteAccount,
            child: Text(l.privacyDeleteButton),
          ),
        ],
      ),
    );
  }
}
