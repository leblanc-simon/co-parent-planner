import 'package:flutter/material.dart';

import '../../depenses/presentation/depenses_screen.dart';

/// Onglet Dépenses (Lot 10) : comptes partagés de la famille active. L'onglet n'est rendu
/// que si la famille a activé le module et que le membre y a droit (voir `HomeShell`).
class DepensesTab extends StatelessWidget {
  const DepensesTab({super.key});

  @override
  Widget build(BuildContext context) => const DepensesScreen();
}
