#!/usr/bin/env python3
"""Génère les coupes statiques des polices du design system (cdc §8).

Les deux fichiers sources sont des polices **variables**. Flutter ne pilote pas
leurs axes depuis `TextStyle.fontWeight` : sans coupe statique déclarée avec un
descripteur `weight:`, chaque famille n'est enregistrée que sous son instance par
défaut — pour Bricolage Grotesque, `wght=800 / opsz=96`, c'est-à-dire un ExtraBold
display pour *tous* les titres, quelle que soit la graisse demandée (revue mobile
2026-08, constat C54).

Ce script fige les instances réellement employées par l'application. Il n'est
nécessaire que pour **régénérer** les coupes (mise à jour d'une police, nouvelle
graisse) : les fichiers produits sont commités.

    pip install fonttools && python3 tool/build_fonts.py

`opsz` (Bricolage) est figé sur la taille où la graisse est employée : une coupe
statique ne peut pas suivre la taille du texte comme le fait
`font-optical-sizing: auto` côté web.
"""

import subprocess
import sys
from pathlib import Path

from fontTools.ttLib import TTFont

FONTS = Path(__file__).resolve().parent.parent / 'assets' / 'fonts'

# (source, famille, style, axes figés) — voir assets/fonts/README.md pour les usages.
CUTS = [
    ('BricolageGrotesque.ttf', 'Bricolage Grotesque', 'SemiBold',
     ['wght=600', 'opsz=21', 'wdth=100']),
    ('BricolageGrotesque.ttf', 'Bricolage Grotesque', 'Bold',
     ['wght=700', 'opsz=24', 'wdth=100']),
    ('BricolageGrotesque.ttf', 'Bricolage Grotesque', 'ExtraBold',
     ['wght=800', 'opsz=18', 'wdth=100']),
    ('HankenGrotesk.ttf', 'Hanken Grotesk', 'Regular', ['wght=400']),
    ('HankenGrotesk.ttf', 'Hanken Grotesk', 'SemiBold', ['wght=600']),
    ('HankenGrotesk.ttf', 'Hanken Grotesk', 'Bold', ['wght=700']),
]


def rename(path: Path, family: str, style: str) -> None:
    """Réécrit la table `name` de la coupe.

    L'instanciation ne la touche pas : les trois coupes de Bricolage s'annonçaient
    toutes « Bricolage Grotesque 96pt ExtraBold », nom de l'instance par défaut de
    la police variable. Sans conséquence sur le rendu — Flutter enregistre chaque
    fichier sous le `weight:` déclaré au `pubspec.yaml` — mais trompeur partout
    ailleurs (inspection du fichier, installation système, rapport de build).
    """
    font = TTFont(path)
    compact = family.replace(' ', '')
    # Convention RIBBI : seuls Regular/Bold tiennent dans le couple famille+style
    # hérité ; les autres graisses ouvrent leur propre famille héritée.
    legacy_family = family if style in ('Regular', 'Bold') else f'{family} {style}'
    legacy_style = style if style in ('Regular', 'Bold') else 'Regular'
    names = {
        1: legacy_family,
        2: legacy_style,
        4: f'{family} {style}',
        6: f'{compact}-{style}',
        16: family,
        17: style,
    }
    for name_id, value in names.items():
        font['name'].setName(value, name_id, 3, 1, 0x409)
        font['name'].setName(value, name_id, 1, 0, 0)
    font.save(path)


def main() -> int:
    for source, family, style, axes in CUTS:
        src = FONTS / 'src' / source
        if not src.exists():
            print(f'source absente : {src}', file=sys.stderr)
            return 1
        output = FONTS / f'{family.replace(" ", "")}-{style}.ttf'
        subprocess.run(
            [sys.executable, '-m', 'fontTools.varLib.instancer',
             str(src), *axes, '-o', str(output), '-q'],
            check=True,
        )
        rename(output, family, style)
        print(f'{output.name} ← {source} ({", ".join(axes)})')

    return 0


if __name__ == '__main__':
    raise SystemExit(main())
