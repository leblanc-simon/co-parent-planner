import 'package:flutter/material.dart';

import 'app_colors.dart';

/// Typographies du design system (cdc §8) : Bricolage Grotesque (titres),
/// Hanken Grotesk (corps). Polices auto-hebergees (pubspec `fonts:`).
abstract final class AppFonts {
  static const String display = 'Bricolage Grotesque';
  static const String body = 'Hanken Grotesk';
}

/// Echelle typographique mobile, mappee sur le `TextTheme` Material.
abstract final class AppTypography {
  static const TextTheme textTheme = TextTheme(
    // Titres — Bricolage Grotesque
    displaySmall: TextStyle(
      fontFamily: AppFonts.display,
      fontSize: 30,
      fontWeight: FontWeight.w700,
      letterSpacing: -0.6,
      height: 1.05,
      color: AppColors.ink,
    ),
    headlineSmall: TextStyle(
      fontFamily: AppFonts.display,
      fontSize: 22,
      fontWeight: FontWeight.w700,
      height: 1.1,
      color: AppColors.ink,
    ),
    titleLarge: TextStyle(
      fontFamily: AppFonts.display,
      fontSize: 21,
      fontWeight: FontWeight.w600,
      height: 1.25,
      color: AppColors.ink,
    ),
    titleMedium: TextStyle(
      fontFamily: AppFonts.display,
      fontSize: 18,
      fontWeight: FontWeight.w700,
      height: 1.1,
      color: AppColors.ink,
    ),
    // Corps — Hanken Grotesk
    bodyLarge: TextStyle(
      fontFamily: AppFonts.body,
      fontSize: 15,
      fontWeight: FontWeight.w600,
      color: AppColors.ink,
    ),
    bodyMedium: TextStyle(
      fontFamily: AppFonts.body,
      fontSize: 14,
      fontWeight: FontWeight.w400,
      height: 1.45,
      color: AppColors.ink,
    ),
    bodySmall: TextStyle(
      fontFamily: AppFonts.body,
      fontSize: 13,
      fontWeight: FontWeight.w400,
      color: AppColors.muted,
    ),
    labelLarge: TextStyle(
      fontFamily: AppFonts.body,
      fontSize: 15,
      fontWeight: FontWeight.w600,
    ),
    labelMedium: TextStyle(
      fontFamily: AppFonts.body,
      fontSize: 13,
      fontWeight: FontWeight.w600,
      color: AppColors.label,
    ),
    labelSmall: TextStyle(
      fontFamily: AppFonts.body,
      fontSize: 11,
      fontWeight: FontWeight.w600,
    ),
  );

  /// Eyebrow / overline : Hanken, majuscules, lettrage espace, ton attenue.
  static const TextStyle eyebrow = TextStyle(
    fontFamily: AppFonts.body,
    fontSize: 12,
    fontWeight: FontWeight.w700,
    letterSpacing: 0.84,
    color: AppColors.muted2,
  );
}
