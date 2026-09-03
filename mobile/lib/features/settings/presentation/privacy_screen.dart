import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/error/error_text.dart';
import '../../../core/storage/storage_providers.dart';
import '../../../core/widgets/confirm_dialog.dart';
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
  Timer? _poll;

  /// Cadence de scrutation tant que l'export est `pending`. L'archive est produite
  /// par un worker Messenger : quelques secondes en général, davantage sur une
  /// grosse famille.
  static const _pollInterval = Duration(seconds: 5);

  @override
  void initState() {
    super.initState();
    // L'opération ne vivait que dans l'état du widget : un retour arrière, une
    // bascule d'onglet ou un kill de l'app effaçait l'identifiant, et l'archive
    // générée devenait inatteignable — l'API n'expose aucune liste des opérations
    // du sujet, et aucun e-mail « export prêt » n'existe (constat C52).
    unawaited(_restoreExport());
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  Future<void> _restoreExport() async {
    final storage = ref.read(secureStorageProvider);
    final id = await storage.readExportOperationId();
    if (id == null || !mounted) return;
    try {
      final op = await ref.read(rgpdRepositoryProvider).exportStatus(id);
      if (mounted) _apply(op);
    } on ApiException catch (e) {
      // L'écran s'ouvre sur son état neutre plutôt que sur une erreur que
      // l'utilisateur n'a pas provoquée. Seule une opération que le serveur ne
      // connaît plus est oubliée : hors-ligne, l'identifiant reste valide et
      // resservira au prochain passage.
      if (e.statusCode == 404 || e.statusCode == 410) {
        await storage.writeExportOperationId(null);
      }
    }
  }

  /// Retient l'opération, la persiste et (re)cale la scrutation sur son statut.
  void _apply(api.MeExport op) {
    setState(() => _export = op);
    final done = op.status == 'done';
    unawaited(
      ref
          .read(secureStorageProvider)
          .writeExportOperationId(done ? null : op.id),
    );
    if (done) {
      _poll?.cancel();
      _poll = null;
    } else {
      _poll ??= Timer.periodic(_pollInterval, (_) {
        if (!_busy) unawaited(_refreshExport());
      });
    }
  }

  Future<void> _run(Future<void> Function() action) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = errorText(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _requestExport() => _run(() async {
    final op = await ref.read(rgpdRepositoryProvider).requestExport();
    if (mounted) _apply(op);
  });

  Future<void> _refreshExport() => _run(() async {
    final id = _export?.id;
    if (id == null) return;
    final op = await ref.read(rgpdRepositoryProvider).exportStatus(id);
    if (mounted) _apply(op);
  });

  Future<void> _deleteAccount() async {
    final l = AppL10n.of(context);
    final confirmed = await confirmAction(
      context,
      title: l.privacyDeleteConfirmTitle,
      body: l.privacyDeleteConfirmBody,
      confirmLabel: l.privacyDeleteConfirm,
      confirmKey: const ValueKey('rgpd-delete-confirm'),
      destructive: true,
    );
    if (!confirmed) return;
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
