import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/liquid_glass.dart';
import '../../../core/widgets/offline_banner.dart';
import '../../../l10n/app_localizations.dart';

/// Shell a onglets (US-HOME-1). `StatefulShellRoute.indexedStack` conserve
/// l'etat de chaque onglet. Barre « liquid glass » (verre sur iOS, opaque crème
/// sur Android).
class HomeShell extends StatelessWidget {
  const HomeShell({required this.navigationShell, super.key});

  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final isIos = Theme.of(context).platform == TargetPlatform.iOS;
    // Composition maquette (SHELL-03) : Accueil · Garde · Agenda · Enfants.
    // L'accès aux Paramètres passe par l'engrenage d'en-tête de l'accueil (U1-03),
    // plus d'onglet Réglages. L'onglet Garde reste présent même pour une famille
    // unie (l'écran affiche alors l'état « famille réunie ») : le nombre de branches
    // de l'indexedStack doit rester stable.
    final items = [
      (icon: Icons.home_outlined, label: l.tabHome),
      (icon: Icons.calendar_today_outlined, label: l.tabCustody),
      (icon: Icons.view_agenda_outlined, label: l.tabAgenda),
      (icon: Icons.people_outline, label: l.tabNotes),
    ];

    return Scaffold(
      body: Column(
        children: [
          const OfflineBanner(),
          Expanded(child: navigationShell),
        ],
      ),
      bottomNavigationBar: LiquidGlass(
        enableBlur: isIos,
        border: const Border(top: BorderSide(color: AppColors.divider)),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(6, 5, 6, 0),
            child: Row(
              children: [
                for (var i = 0; i < items.length; i++)
                  Expanded(
                    child: _TabItem(
                      key: ValueKey('tab-$i'),
                      icon: items[i].icon,
                      label: items[i].label,
                      selected: navigationShell.currentIndex == i,
                      onTap: () => navigationShell.goBranch(
                        i,
                        initialLocation: i == navigationShell.currentIndex,
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _TabItem extends StatelessWidget {
  const _TabItem({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
    super.key,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.ink : AppColors.muted2;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        // SHELL-04 : on garde l'icône filaire (contour) même à l'actif ; seule la
        // couleur change (encre/gris).
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: color, size: 24),
            const SizedBox(height: 4),
            Text(
              label,
              style: TextStyle(
                fontSize: 10.5,
                fontWeight: FontWeight.w600,
                color: color,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
