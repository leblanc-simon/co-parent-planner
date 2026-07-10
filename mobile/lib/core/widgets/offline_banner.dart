import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../l10n/app_localizations.dart';
import '../sync/connectivity_provider.dart';
import '../sync/outbox_service.dart';
import '../sync/sync_providers.dart';
import '../theme/app_colors.dart';

/// Bandeau global d'état offline / synchro (Lot 8 §2.2, US-OFF-1/5). Tient l'outbox
/// vivant (enregistre le rejeu auto au retour réseau) et déclenche un flush initial.
/// Affiche « Hors-ligne » quand la connexion est perdue, sinon « N en attente de synchro »
/// tant que des mutations restent en file. Rien quand tout est synchronisé et en ligne.
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
      ref.read(outboxServiceProvider).flush();
    });
  }

  @override
  Widget build(BuildContext context) {
    // Garde l'outbox (et son écoute du retour réseau) vivant tant que le shell est monté.
    ref.watch(outboxServiceProvider);
    final l = AppL10n.of(context);
    final online = ref.watch(connectivityProvider).value ?? true;
    final pending = ref.watch(pendingSyncCountProvider).value ?? 0;

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

class _Bar extends StatelessWidget {
  const _Bar({required this.color, required this.icon, required this.label});

  final Color color;
  final IconData icon;
  final String label;

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
            ],
          ),
        ),
      ),
    );
  }
}
