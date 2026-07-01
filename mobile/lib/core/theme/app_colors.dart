import 'package:flutter/material.dart';

/// Tokens de couleur du design system (cdc §8). Valeurs hex exactes extraites
/// de `backend/assets/styles/app.css` et de la maquette mobile.
///
/// Cote mobile, le fond d'ecran est `surface` (#F4F1EA), PAS `appBg` (#E5E0D6,
/// reserve au desktop). Parent A = terracotta (`accent`), Parent B = teal
/// (`positive`), famille liee (overlay) = `linked`.
abstract final class AppColors {
  // Surfaces & encre
  static const Color appBg = Color(0xFFE5E0D6);
  static const Color surface = Color(0xFFF4F1EA);
  static const Color card = Color(0xFFFFFFFF);
  static const Color ink = Color(0xFF292621);
  static const Color ink2 = Color(0xFF544E45);
  static const Color ink3 = Color(0xFF3A352E);
  static const Color muted = Color(0xFF857E71);
  static const Color muted2 = Color(0xFFA39B8C);
  static const Color label = Color(0xFF6F685C);
  static const Color sidebar = Color(0xFF2B2722);

  // Accents
  static const Color accent = Color(0xFFC2643D); // terracotta — parent A
  static const Color accentStrong = Color(0xFFA8512B);
  static const Color accentSoft = Color(0xFFFBF4EE);
  static const Color accentSoft2 = Color(0xFFF6E0D5);
  static const Color positive = Color(0xFF2E7D71); // teal — parent B
  static const Color positiveStrong = Color(0xFF226B60);
  static const Color positiveSoft = Color(0xFFDCECE8);
  static const Color linked = Color(0xFF9C6FB0); // famille liee (overlay)
  static const Color linkedSoft = Color(0xFFEFE6F4); // fond doux overlay

  // Bordures & separateurs
  static const Color border = Color(0xFFEFEAE0); // carte
  static const Color border2 = Color(0xFFEAE4D9); // pilule
  static const Color borderField = Color(0xFFE3DCD0); // champ
  static const Color divider = Color(0xFFECE5D9);
  static const Color grip = Color(0xFFD8D1C3); // grabber des bottom sheets

  // Etats
  static const Color error = Color(0xFFC75D5D); // = categorie sante
  static const Color glassNav = Color(0xEBFCFAF5); // crème 92% — tab bar verre

  // Categories agenda (texte / fond pastille) — reservees (Lots 2/4).
  static const Color catSport = Color(0xFF5E7CB8);
  static const Color catSportSoft = Color(0xFFE4EAF5);
  static const Color catMusic = Color(0xFF9C6FB0);
  static const Color catMusicSoft = Color(0xFFEFE6F4);
  static const Color catSchool = Color(0xFFC99A3F);
  static const Color catSchoolSoft = Color(0xFFF6EDDA);
  static const Color catHealth = Color(0xFFC75D5D);
  static const Color catHealthSoft = Color(0xFFF6E3E3);
  static const Color catOther = Color(0xFF857E71);
  static const Color catOtherSoft = Color(0xFFECE5D9);
}
