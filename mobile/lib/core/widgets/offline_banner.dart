import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../l10n/app_localizations.dart';
import '../session/session_scope.dart';
import '../storage/drift/database.dart';
import '../sync/connectivity_provider.dart';
import '../sync/outbox_service.dart';
import '../sync/sync_providers.dart';
import '../theme/app_colors.dart';

/// Bandeau global d'état offline / synchro (Lot 8 §2.2, US-OFF-1/5). Tient l'outbox
/// vivant (enregistre le rejeu auto au retour réseau) et déclenche un flush initial.
///
/// Trois états, par priorité décroissante : mutations **refusées** par le serveur
/// (action requise), **hors-ligne**, mutations **en attente**. Rien quand tout est
/// synchronisé et en ligne.
///
/// Le premier état est celui qui manquait : `rejected` n'était branché sur aucune
/// UI, donc une saisie hors-ligne refusée (droits, conflit, validation)
/// disparaissait sans notification ni moyen de relance, alors que `retry()` et
/// `discard()` étaient écrits et testés (revue mobile 2026-08, constats C10/C64).
class OfflineBanner extends ConsumerStatefulWidget {
  const OfflineBanner({super.key});

  @override
  ConsumerState<OfflineBanner> createState() => _OfflineBannerState();
}

class _OfflineBannerState extends ConsumerState<OfflineBanner> {
  @override
  void initState() {
    super.initState();
    // Active le rejeu auto (écoute connectivité) + tentative initiale de synchro.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final userId = ref.read(currentUserIdProvider);
      if (userId != null) ref.read(outboxServiceProvider).flush(userId);
    });
  }

  @override
  Widget build(BuildContext context) {
    // Garde l'outbox (et son écoute du retour réseau) vivant tant que le shell est monté.
    ref.watch(outboxServiceProvider);
    final l = AppL10n.of(context);
    final online = ref.watch(connectivityProvider).value ?? true;
    final pending = ref.watch(pendingSyncCountProvider).value ?? 0;
    final rejected = ref.watch(rejectedSyncCountProvider).value ?? 0;

    if (rejected > 0) {
      return _Bar(
        key: const ValueKey('sync-rejected-banner'),
        color: AppColors.catHealth,
        icon: Icons.error_outline,
        label: l.syncRejected(rejected),
        action: l.syncRejectedAction,
        onAction: () => showModalBottomSheet<void>(
          context: context,
          isScrollControlled: true,
          builder: (_) => const RejectedSyncSheet(),
        ),
      );
    }
    if (!online) {
      return _Bar(
        color: AppColors.ink,
        icon: Icons.cloud_off_outlined,
        label: l.offlineBanner,
      );
    }
    if (pending > 0) {
      return _Bar(
        color: AppColors.accent,
        icon: Icons.sync,
        label: l.syncPending(pending),
      );
    }
    return const SizedBox.shrink();
  }
}

/// Liste des mutations refusées : relancer ou abandonner (US-OFF-5).
class RejectedSyncSheet extends ConsumerWidget {
  const RejectedSyncSheet({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final entries =
        ref.watch(rejectedSyncEntriesProvider).value ?? const <OutboxEntry>[];

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              l.syncRejectedTitle,
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 6),
            Text(
              l.syncRejectedHelp,
              style: const TextStyle(color: AppColors.ink2, fontSize: 13),
            ),
            const SizedBox(height: 12),
            if (entries.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 16),
                child: Text(l.syncRejectedEmpty),
              )
            else
              Flexible(
                child: ListView.separated(
                  shrinkWrap: true,
                  itemCount: entries.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (_, i) => _RejectedTile(
                    entry: entries[i],
                    key: ValueKey(entries[i].id),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _RejectedTile extends ConsumerWidget {
  const _RejectedTile({required this.entry, super.key});

  final OutboxEntry entry;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final outbox = ref.read(outboxServiceProvider);
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text('${entry.method} ${entry.path}'),
      subtitle: Text(l.syncRejectedReason(entry.lastError ?? 'other')),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextButton(
            key: ValueKey('sync-retry-${entry.id}'),
            onPressed: () => outbox.retry(entry.id),
            child: Text(l.actionRetry),
          ),
          TextButton(
            key: ValueKey('sync-discard-${entry.id}'),
            onPressed: () => outbox.discard(entry.id),
            child: Text(l.actionDiscard),
          ),
        ],
      ),
    );
  }
}

class _Bar extends StatelessWidget {
  const _Bar({
    required this.color,
    required this.icon,
    required this.label,
    this.action,
    this.onAction,
    super.key,
  });

  final Color color;
  final IconData icon;
  final String label;
  final String? action;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: color,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          child: Row(
            children: [
              Icon(icon, size: 16, color: Colors.white),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  label,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              if (action != null && onAction != null)
                TextButton(
                  onPressed: onAction,
                  style: TextButton.styleFrom(
                    foregroundColor: Colors.white,
                    // 44 pt : cible tactile minimale (§8 / a11y).
                    minimumSize: const Size(64, 44),
                  ),
                  child: Text(action!),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
