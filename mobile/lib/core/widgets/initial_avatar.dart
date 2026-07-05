import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_typography.dart';

/// Avatar rond coloré portant une initiale en Bricolage Grotesque blanc
/// (design system §8). Remplace les `CircleAvatar` vides ; décliné par taille.
class InitialAvatar extends StatelessWidget {
  const InitialAvatar({
    required this.initial,
    required this.color,
    this.size = 40,
    this.fontSize,
    super.key,
  });

  /// Construit depuis une couleur hex `#RRGGBB` (repli gris si invalide) et un
  /// libellé dont on dérive l'initiale.
  factory InitialAvatar.from({
    required String? hexColor,
    required String? label,
    double size = 40,
    double? fontSize,
    Key? key,
  }) {
    return InitialAvatar(
      initial: initialOf(label),
      color: parseHex(hexColor),
      size: size,
      fontSize: fontSize,
      key: key,
    );
  }

  final String initial;
  final Color color;
  final double size;
  final double? fontSize;

  /// Parse une couleur hex `#RRGGBB` ; repli gris (`muted2`) si invalide/vide.
  static Color parseHex(String? hex) {
    if (hex == null || hex.isEmpty) return AppColors.muted2;
    final value = int.tryParse(hex.replaceFirst('#', ''), radix: 16);
    return value == null ? AppColors.muted2 : Color(0xFF000000 | value);
  }

  /// Première lettre significative d'un libellé, en majuscule (repli « ? »).
  static String initialOf(String? label) {
    final trimmed = label?.trim() ?? '';
    return trimmed.isEmpty ? '?' : trimmed.characters.first.toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(color: color, shape: BoxShape.circle),
      child: Text(
        initial,
        style: TextStyle(
          fontFamily: AppFonts.display,
          fontSize: fontSize ?? size * 0.44,
          fontWeight: FontWeight.w700,
          color: Colors.white,
          height: 1,
        ),
      ),
    );
  }
}
