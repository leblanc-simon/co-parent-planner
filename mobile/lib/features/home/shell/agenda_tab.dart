import 'package:flutter/material.dart';

import '../../../core/theme/app_typography.dart';
import '../../../l10n/app_localizations.dart';

/// Placeholder onglet Agenda (contenu reel au Lot 4).
class AgendaTab extends StatelessWidget {
  const AgendaTab({super.key});

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(l.tabAgenda)),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Text(
            l.homeUpcomingEmpty,
            textAlign: TextAlign.center,
            style: AppTypography.textTheme.bodyMedium,
          ),
        ),
      ),
    );
  }
}
