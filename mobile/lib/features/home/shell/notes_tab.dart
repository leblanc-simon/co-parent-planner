import 'package:flutter/material.dart';

import '../../../core/theme/app_typography.dart';
import '../../../l10n/app_localizations.dart';

/// Placeholder onglet Enfants/Notes (contenu reel au Lot 2).
class NotesTab extends StatelessWidget {
  const NotesTab({super.key});

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(l.tabNotes)),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Text(
            l.homeNotesEmpty,
            textAlign: TextAlign.center,
            style: AppTypography.textTheme.bodyMedium,
          ),
        ),
      ),
    );
  }
}
