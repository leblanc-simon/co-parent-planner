import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/liquid_glass.dart';
import '../../../core/widgets/offline_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../../family/application/family_context.dart';
import '../../members/data/members_repository.dart';

/// Shell a onglets (US-HOME-1). `StatefulShellRoute.indexedStack` conserve
/// l'etat de chaque onglet. Barre « liquid glass » (verre sur iOS, opaque crème
/// sur Android).
class HomeShell extends ConsumerWidget {
  const HomeShell({required this.navigationShell, super.key});

  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final isIos = Theme.of(context).platform == TargetPlatform.iOS;
    final family = ref.watch(familyContextProvider);
    // Un onglet n'est masqué que sur un refus **explicite** : le serveur renvoie
    // toujours les six domaines avec leur niveau, donc l'absence de valeur signifie
    // « pas encore chargé » (ou membres injoignables hors-ligne), jamais « refusé ».
    // Masquer dans ce cas ferait clignoter la barre au démarrage et la viderait de
    // ses onglets hors-ligne. Les boutons de mutation appliquent la règle inverse :
    // masqués tant que le droit n'est pas confirmé, un 403 étant pire qu'une action
    // qui apparaît un instant plus tard.
    bool mayView(String domain) => ref.watch(rightProvider(domain)) != 'none';
    // Composition maquette (SHELL-03) : Accueil · Garde · Agenda · Enfants.
    // L'accès aux Paramètres passe par l'engrenage d'en-tête de l'accueil (U1-03),
    // plus d'onglet Réglages.
    //
    // Les branches de l'`indexedStack` restent au nombre de quatre — leur nombre
    // doit être stable — mais un onglet dont l'écran ne peut rien montrer n'est plus
    // rendu : la spec impose de masquer Garde pour une famille **unie** (« l'onglet
    // est absent côté UI », web ET mobile) et le web masque en plus tout onglet dont
    // le domaine est refusé. Un bouton qui ne mène qu'à un écran de refus n'informe
    // personne (revue mobile 2026-08, constat C33 ; revue web 6.12).
    final items =
        <({int branch, IconData icon, String label, bool visible})>[
              (
                branch: 0,
                icon: Icons.home_outlined,
                label: l.tabHome,
                visible: true,
              ),
              (
                branch: 1,
                icon: Icons.calendar_today_outlined,
                label: l.tabCustody,
                // Le type de famille, lui, est connu localement et sans délai.
                visible:
                    family != null && !family.isIntact && mayView('custody'),
              ),
              (
                branch: 2,
                icon: Icons.view_agenda_outlined,
                label: l.tabAgenda,
                visible: mayView('agenda'),
              ),
              (
                branch: 3,
                icon: Icons.people_outline,
                label: l.tabNotes,
                visible: mayView('children_notes'),
              ),
            ]
            .where((i) => i.visible || i.branch == navigationShell.currentIndex)
            .toList();

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
                for (final item in items)
                  Expanded(
                    child: _TabItem(
                      // La clé reste celle de la **branche**, pas du rang affiché :
                      // masquer un onglet ne doit pas renommer les autres.
                      key: ValueKey('tab-${item.branch}'),
                      icon: item.icon,
                      label: item.label,
                      selected: navigationShell.currentIndex == item.branch,
                      onTap: () => navigationShell.goBranch(
                        item.branch,
                        initialLocation:
                            item.branch == navigationShell.currentIndex,
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
