import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Garde-fous d'i18n (revue mobile 2026-08, étape 8).
///
/// Deux règles que la revue a trouvées violées et que rien ne surveillait :
/// la moitié du catalogue français était saisie **sans diacritiques** (C57), et
/// trois messages destinés à l'utilisateur étaient écrits en français **dans le
/// code** (C19), donc intraduisibles sans changement de code — ce que l'invariant
/// i18n du `CLAUDE.md` interdit explicitement.
void main() {
  group('catalogue ARB', () {
    late String arb;

    setUpAll(() {
      arb = File('lib/l10n/app_fr.arb').readAsStringSync();
    });

    test('aucun mot français écrit sans son accent', () {
      // Mots relevés par la revue, et leurs voisins de même famille. La liste est
      // volontairement explicite : une heuristique générale signalerait « Parent »
      // ou « Continuer », corrects sans accent.
      const forbidden = [
        'Reessayer',
        'reessayer',
        'Verifiez',
        'verifiez',
        'Verification',
        'verification',
        'verifie',
        'Accedez',
        'accedez',
        'oublie',
        'Creer',
        'creer',
        'Creez',
        'Telephone',
        'telephone',
        'Deja',
        'separes',
        'Reglages',
        'reglages',
        'Dernieres',
        'Revoquer',
        'revoquee',
        'Role',
        'Edition',
        'reinitialis',
        'caracteres',
        'evenement',
        'deconnecter',
        'expiree',
        'expire',
        'reunie',
        'decider',
        'envoyee',
        'recu',
        'Echanges',
        'Francais',
        'reseau',
        'Reponse',
      ];
      final hits = <String>[];
      for (final word in forbidden) {
        if (RegExp('\\b$word').hasMatch(arb)) hits.add(word);
      }

      expect(
        hits,
        isEmpty,
        reason:
            'Valeurs ARB à réaccentuer (mots trouvés : ${hits.join(', ')}). '
            'Le catalogue fr-FR est la langue livrée : il doit être correct.',
      );
    });
  });

  test('aucune chaîne accentuée en dur hors du catalogue', () {
    // Une chaîne destinée à l'utilisateur porte presque toujours un accent en
    // français ; une chaîne technique (clé, chemin, en-tête HTTP) n'en porte
    // jamais. Le critère est donc précis et sans faux positif connu.
    final accented = RegExp('[àâäçéèêëîïôöùûüÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŒœ]');
    final offenders = <String>[];

    for (final entity in Directory('lib').listSync(recursive: true)) {
      if (entity is! File || !entity.path.endsWith('.dart')) continue;
      final path = entity.path;
      // `lib/l10n` EST le catalogue ; `lib/api/generated` est produit depuis
      // l'OpenAPI et porte les descriptions françaises du contrat.
      if (path.contains('/l10n/') || path.contains('/api/generated/')) continue;

      var lineNo = 0;
      for (final raw in entity.readAsLinesSync()) {
        lineNo++;
        final line = raw.trimLeft();
        // Les commentaires du projet sont en français : on ne juge que le code.
        if (line.startsWith('//') ||
            line.startsWith('*') ||
            line.startsWith('/*')) {
          continue;
        }
        final code = line.split('//').first;
        for (final match in RegExp(r"'([^'\\]*)'").allMatches(code)) {
          final literal = match.group(1)!;
          if (accented.hasMatch(literal)) {
            offenders.add('$path:$lineNo → « $literal »');
          }
        }
      }
    }

    expect(
      offenders,
      isEmpty,
      reason:
          'Chaînes françaises hors ARB :\n${offenders.join('\n')}\n'
          'Les déplacer dans lib/l10n/app_fr.arb (invariant i18n du CLAUDE.md).',
    );
  });
}
