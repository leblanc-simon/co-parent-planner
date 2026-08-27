import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';

/// Écran d'attente pendant la restauration de session (`AuthRestoring`).
///
/// Il existe pour ne rien montrer de faux : avant la revue mobile 2026-08,
/// `main()` attendait `GET /api/me` avant `runApp()` — donc une fenêtre vide sur
/// réseau lent (constat C24) — et démarrer directement sur l'écran de connexion
/// l'aurait fait clignoter devant un utilisateur déjà connecté.
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: AppColors.surface,
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              'CoParentPlanner',
              style: TextStyle(
                fontFamily: AppFonts.display,
                fontSize: 24,
                fontWeight: FontWeight.w700,
                color: AppColors.ink,
              ),
            ),
            SizedBox(height: 20),
            SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(strokeWidth: 2.2),
            ),
          ],
        ),
      ),
    );
  }
}
