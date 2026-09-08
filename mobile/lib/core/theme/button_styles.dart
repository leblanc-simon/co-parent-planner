import 'package:flutter/material.dart';

/// Styles de bouton derogeant a la largeur pleine posee par le theme.
///
/// `AppTheme.light()` donne aux `FilledButton`/`OutlinedButton` une
/// `minimumSize: Size.fromHeight(50)` — soit une largeur **infinie** : c'est ce
/// qui produit les boutons pleine largeur des formulaires. Pose tel quel dans
/// une `Row`, un bouton reclame alors une largeur infinie que la `Row` ne peut
/// satisfaire : le layout leve `BoxConstraints forces an infinite width` et
/// **tout l'ecran reste blanc** (constate sur la fiche enfant, la feuille de
/// jour du planning et le detail d'echange).
///
/// Un bouton place cote a cote avec un autre widget doit donc reprendre sa
/// largeur intrinseque, en gardant la hauteur de 50 du systeme de design.
abstract final class AppButtonStyles {
  /// Hauteur du systeme de design, largeur libre : pour un bouton en `Row`.
  static const Size intrinsicWidth = Size(0, 50);

  static ButtonStyle filledInRow() =>
      FilledButton.styleFrom(minimumSize: intrinsicWidth);

  static ButtonStyle outlinedInRow() =>
      OutlinedButton.styleFrom(minimumSize: intrinsicWidth);
}
