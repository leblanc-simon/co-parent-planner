import 'package:coparentplanner/features/custody/presentation/parent_initials.dart';
import 'package:flutter_test/flutter_test.dart';

/// Jumeau de `backend/tests/Unit/Web/ParentInitialsTest.php` : **les mêmes cas doivent figurer des
/// deux côtés** (cdc §8 — la règle est normative et implémentée dans les deux clients).
void main() {
  // (nomA, nomB, marqueurA attendu, marqueurB attendu)
  const cases = <String, (String, String, String, String)>{
    'prénoms distincts': ('Sophie Martin', 'Marc Durand', 'S', 'M'),
    'collision sur la première lettre': ('Sophie', 'Simon', 'So', 'Si'),
    'collision sur deux lettres': ('Sophie', 'Sophia', 'A', 'B'),
    'accent conservé et distinctif': ('Élodie', 'Eric', 'É', 'E'),
    'casse normalisée': ('sophie martin', 'MARC', 'S', 'M'),
    'repli e-mail': (
      'sophie.martin@example.test',
      'marc@example.test',
      'S',
      'M',
    ),
    'repli e-mail en collision': (
      'sophie@example.test',
      'simon@example.test',
      'So',
      'Si',
    ),
    'homonymes stricts': ('Camille', 'Camille', 'A', 'B'),
    // Plafond de deux lettres : ces deux e-mails ne divergent qu'au 4ᵉ caractère. Un marqueur
    // « Web »/« Web2 » ne tient pas dans une case de calendrier — repli A/B attendu.
    'préfixe commun au-delà de deux lettres': (
      'web@example.com',
      'web2@example.com',
      'A',
      'B',
    ),
    'nom vide': ('', 'Marc', 'A', 'B'),
    'espaces seuls': ('   ', '   ', 'A', 'B'),
  };

  group('ParentInitials.pair', () {
    cases.forEach((label, data) {
      final (nameA, nameB, expectedA, expectedB) = data;

      test(label, () {
        final pair = ParentInitials.pair(nameA, nameB);

        expect(pair.a, expectedA);
        expect(pair.b, expectedB);
      });
    });

    // L'invariant qui porte l'accessibilité : quels que soient les noms, les deux marqueurs
    // diffèrent — sinon la case de calendrier redevient muette sans la couleur.
    cases.forEach((label, data) {
      final (nameA, nameB, _, _) = data;

      test('marqueurs toujours distincts — $label', () {
        final pair = ParentInitials.pair(nameA, nameB);

        expect(pair.a, isNotEmpty);
        expect(pair.b, isNotEmpty);
        expect(pair.a, isNot(pair.b));
      });
    });
  });
}
