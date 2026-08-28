import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../l10n/app_localizations.dart';
import '../application/family_context.dart';
import '../data/family_repository.dart';

/// Libellé localisé d'un type de famille (`separated` / `intact`).
///
/// Les deux feuilles de bascule affichaient `f.type` **brut** : l'utilisateur lisait
/// « separated » sous le nom de sa famille, chaîne anglaise en snake_case qu'aucun
/// catalogue ne traduira jamais (revue mobile 2026-08, constat C51). Les clés
/// existaient déjà et servaient ailleurs, dans les mêmes fichiers.
///
/// Une valeur inconnue rend une chaîne **vide** plutôt que la valeur d'énumération :
/// mieux vaut ne rien dire que d'exposer une clé technique.
String familyTypeLabel(AppL10n l, String type) => switch (type) {
  'separated' => l.familyTypeSeparated,
  'intact' => l.familyTypeIntact,
  _ => '',
};

/// US-SCOPE-1 — bascule de famille active via une feuille modale.
///
/// Feuille unique : l'accueil et les Paramètres en portaient chacun une copie, qui
/// avaient déjà divergé (une seule des deux invalidait les membres).
Future<void> showFamilySwitcher(BuildContext context, WidgetRef ref) async {
  final families = await ref.read(myFamiliesProvider.future);
  if (!context.mounted) return;
  final l = AppL10n.of(context);

  await showModalBottomSheet<void>(
    context: context,
    builder: (sheetContext) => SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (final f in families)
            ListTile(
              key: ValueKey('family-switch-${f.id}'),
              title: Text(f.name),
              subtitle: switch (familyTypeLabel(l, f.type)) {
                '' => null,
                final label => Text(label),
              },
              onTap: () async {
                await ref
                    .read(familyContextProvider.notifier)
                    .setActive(
                      FamilyContext(
                        familyId: f.id,
                        familyName: f.name,
                        type: f.type,
                      ),
                    );
                if (sheetContext.mounted) Navigator.of(sheetContext).pop();
              },
            ),
        ],
      ),
    ),
  );
}
