import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_shapes.dart';

/// Pastille de filtre (agenda, échanges) : pilule de 32 pt dans une **cible
/// tactile de 44 pt**.
///
/// Les deux écrans en portaient chacun une copie quasi identique, dont la zone
/// réactive se limitait au dessin : 7 pt de marge autour d'un texte de 13 px,
/// soit ~32 pt de haut, sous les 44 pt des HIG iOS et les 48 dp de Material.
/// Les taps un peu hauts ou bas tombaient dans la liste et ne filtraient rien
/// (revue mobile 2026-08, constat C61).
///
/// La hauteur tapable est portée à 44 pt **sans changer l'aspect** : le
/// `ConstrainedBox` grandit la zone de l'`InkWell`, l'`Align` laisse la pilule à
/// sa taille propre, centrée dedans.
class AppFilterChip extends StatelessWidget {
  const AppFilterChip({
    required this.label,
    required this.selected,
    required this.onTap,
    this.color = AppColors.ink,
    super.key,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  /// Teinte de l'état sélectionné : couleur de la catégorie pour l'agenda,
  /// encre par défaut pour un filtre sans code couleur.
  final Color color;

  /// Minimum recommandé par les HIG iOS (44 pt) ; Material demande 48 dp, mais
  /// six pastilles de 48 pt ne tiennent plus sur la largeur d'un iPhone SE.
  static const double minTapTarget = 44;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadii.pill),
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: minTapTarget),
        child: Align(
          widthFactor: 1,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
            decoration: BoxDecoration(
              color: selected ? color.withValues(alpha: 0.14) : AppColors.card,
              borderRadius: BorderRadius.circular(AppRadii.pill),
              border: Border.all(color: selected ? color : AppColors.border2),
            ),
            child: Text(
              label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: selected ? color : AppColors.muted,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
