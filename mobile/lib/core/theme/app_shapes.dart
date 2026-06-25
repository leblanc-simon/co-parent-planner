import 'package:flutter/material.dart';

import 'app_colors.dart';

/// Rayons du design system (cdc §8) : cartes 14–20, nav 11, pastilles 999,
/// modales/sheets mobile 28, champs 12.
abstract final class AppRadii {
  static const double field = 12;
  static const double nav = 11;
  static const double card = 18;
  static const double cardLg = 22;
  static const double sheet = 28;
  static const double pill = 999;
}

/// Ombres douces (couleur = encre #292621 a faible opacite, jamais du noir pur).
abstract final class AppShadows {
  static const List<BoxShadow> soft = [
    BoxShadow(color: Color(0x0D292621), blurRadius: 2, offset: Offset(0, 1)),
    BoxShadow(color: Color(0x0A292621), blurRadius: 24, offset: Offset(0, 8)),
  ];

  static const List<BoxShadow> pill = [
    BoxShadow(color: Color(0x0D292621), blurRadius: 2, offset: Offset(0, 1)),
  ];

  static const List<BoxShadow> pop = [
    BoxShadow(color: Color(0x24292621), blurRadius: 40, offset: Offset(0, 16)),
  ];

  static const List<BoxShadow> sheet = [
    BoxShadow(color: Color(0x24292621), blurRadius: 40, offset: Offset(0, -10)),
  ];
}

/// Carte standard du design system : fond blanc, bordure douce, rayon 18,
/// ombre `soft`. (Material `Card` ne reproduit pas l'ombre double-couche teintee.)
class AppCard extends StatelessWidget {
  const AppCard({
    required this.child,
    this.padding = const EdgeInsets.all(20),
    this.radius = AppRadii.card,
    super.key,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: padding,
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: AppColors.border),
        boxShadow: AppShadows.soft,
      ),
      child: child,
    );
  }
}
