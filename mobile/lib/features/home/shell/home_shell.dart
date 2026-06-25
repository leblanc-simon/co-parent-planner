import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/liquid_glass.dart';
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
    final items = [
      (icon: Icons.home_outlined, active: Icons.home, label: l.tabHome),
      (
        icon: Icons.calendar_today_outlined,
        active: Icons.calendar_today,
        label: l.tabAgenda,
      ),
      (icon: Icons.people_outline, active: Icons.people, label: l.tabNotes),
      (
        icon: Icons.settings_outlined,
        active: Icons.settings,
        label: l.tabSettings,
      ),
    ];

    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: LiquidGlass(
        enableBlur: isIos,
        border: const Border(top: BorderSide(color: AppColors.divider)),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 6),
            child: Row(
              children: [
                for (var i = 0; i < items.length; i++)
                  Expanded(
                    child: _TabItem(
                      key: ValueKey('tab-$i'),
                      icon: items[i].icon,
                      activeIcon: items[i].active,
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
    required this.activeIcon,
    required this.label,
    required this.selected,
    required this.onTap,
    super.key,
  });

  final IconData icon;
  final IconData activeIcon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.accent : AppColors.muted;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(selected ? activeIcon : icon, color: color, size: 24),
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
