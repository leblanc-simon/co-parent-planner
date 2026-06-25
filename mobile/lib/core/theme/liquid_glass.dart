import 'dart:ui';

import 'package:flutter/material.dart';

import 'app_colors.dart';

/// Effet « liquid glass » iOS (cdc §8) : flou d'arriere-plan + teinte creme.
/// Sur Android, on rend opaque (le blur est couteux et peu Material). A placer
/// dans un `Stack` AU-DESSUS du contenu scrollable (le `BackdropFilter` a besoin
/// de contenu derriere pour flouter).
class LiquidGlass extends StatelessWidget {
  const LiquidGlass({
    required this.child,
    this.sigma = 14,
    this.tint = AppColors.glassNav,
    this.borderRadius = BorderRadius.zero,
    this.border,
    this.enableBlur = true,
    super.key,
  });

  final Widget child;
  final double sigma;
  final Color tint;
  final BorderRadius borderRadius;
  final BoxBorder? border;

  /// Mettre `false` sur Android (rendu opaque sans flou).
  final bool enableBlur;

  @override
  Widget build(BuildContext context) {
    final decorated = DecoratedBox(
      decoration: BoxDecoration(
        color: enableBlur ? tint : Color.alphaBlend(tint, AppColors.surface),
        borderRadius: borderRadius,
        border: border,
      ),
      child: child,
    );

    if (!enableBlur) {
      return ClipRRect(borderRadius: borderRadius, child: decorated);
    }

    return ClipRRect(
      borderRadius: borderRadius,
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: sigma, sigmaY: sigma),
        child: decorated,
      ),
    );
  }
}
