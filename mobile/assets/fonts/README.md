# Polices du design system (cdc §8)

`Bricolage Grotesque` (titres) et `Hanken Grotesk` (corps), auto-hébergées sous
licence OFL (`OFL-Bricolage.txt`, `OFL-Hanken.txt`) pour fonctionner hors-ligne.

## Pourquoi des coupes statiques

`src/` contient les **polices variables** d'origine. Elles ne peuvent pas être
embarquées telles quelles : Flutter ne pilote pas les axes variables depuis
`TextStyle.fontWeight` — cela demande `fontVariations`, qu'aucun `fontWeight`
écrit dans un écran ne déclenche. Déclarée en un seul fichier sans descripteur
`weight:`, une famille n'est enregistrée que sous son **instance par défaut** :
pour Bricolage Grotesque, `wght=800 / opsz=96`, c'est-à-dire le même ExtraBold
display pour tous les titres, quelle que soit la graisse demandée — la hiérarchie
600/700 de la maquette disparaissait (revue mobile 2026-08, constat C54).

Les coupes de ce dossier sont donc des **instances figées**, déclarées une par une
au `pubspec.yaml` avec leur `weight:`. `fontWeight` retrouve alors son
fonctionnement normal, y compris pour les styles écrits directement dans les
écrans.

## Coupes produites

| Fichier | `wght` | `opsz` | Employée par |
|---|---|---|---|
| `BricolageGrotesque-SemiBold.ttf` | 600 | 21 | `titleLarge` (21 px) |
| `BricolageGrotesque-Bold.ttf` | 700 | 24 | `displaySmall` (30), `headlineSmall` (22), `titleMedium` (18), titres d'écrans (19-26), initiales d'avatar |
| `BricolageGrotesque-ExtraBold.ttf` | 800 | 18 | logo de l'écran d'authentification |
| `HankenGrotesk-Regular.ttf` | 400 | — | `bodyMedium`, `bodySmall` |
| `HankenGrotesk-SemiBold.ttf` | 600 | — | `bodyLarge`, `labelLarge/Medium/Small`, eyebrow |
| `HankenGrotesk-Bold.ttf` | 700 | — | libellés en gras des écrans |

`opsz` (l'axe de taille optique de Bricolage) est figé sur la taille où la graisse
est employée : une coupe statique ne peut pas suivre la taille du texte comme le
fait `font-optical-sizing: auto` côté web. L'écart résiduel est de quelques points
optiques sur les titres de 30 px, sans commune mesure avec les 96 pt d'avant.
Ajouter une graisse à la table ci-dessus se fait dans `tool/build_fonts.py`.

## Régénérer

```bash
pip install fonttools     # outil de génération uniquement, pas une dépendance de build
python3 tool/build_fonts.py
```

Les fichiers produits sont **commités** : construire l'application ne demande ni
Python ni fonttools.
