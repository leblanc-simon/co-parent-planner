import 'package:flutter/material.dart';

import '../../l10n/app_localizations.dart';
import '../theme/app_colors.dart';

/// Confirmation nommée d'une action, rendue en `AlertDialog`.
///
/// Idiome unique de l'application : l'écran Confidentialité et la suppression
/// d'un événement en avaient chacun une copie, et deux actions irréversibles de
/// plus en manquaient — la clôture d'un échange, que le serveur interdit de
/// rouvrir (§4.5), et la révocation d'une invitation (constats C46 et C47).
///
/// Rend `false` quand l'utilisateur annule **ou** ferme la boîte hors du bouton
/// (tap à côté, retour Android), qui rendent `null` : seul un appui explicite
/// sur le bouton de confirmation vaut accord.
Future<bool> confirmAction(
  BuildContext context, {
  required String title,
  required String body,
  required String confirmLabel,
  Key? confirmKey,
  bool destructive = false,
}) async {
  final l = AppL10n.of(context);
  final confirmed = await showDialog<bool>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: Text(title),
      content: Text(body),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(dialogContext).pop(false),
          child: Text(l.actionCancel),
        ),
        TextButton(
          key: confirmKey,
          onPressed: () => Navigator.of(dialogContext).pop(true),
          child: Text(
            confirmLabel,
            style: destructive ? const TextStyle(color: AppColors.error) : null,
          ),
        ),
      ],
    ),
  );

  return confirmed ?? false;
}
