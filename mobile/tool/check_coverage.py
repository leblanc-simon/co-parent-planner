#!/usr/bin/env python3
"""Garde-fou de couverture mobile (decision D8 : >= 70 % de lignes).

Parse coverage/lcov.info en EXCLUANT le code genere (client OpenAPI, *.g.dart,
l10n). Usage : python3 tool/check_coverage.py [lcov.info] [seuil].

`flutter test --coverage` n'instrumente que les bibliotheques REELLEMENT chargees
par l'isolat de test : un fichier qu'aucun test n'importe est absent du rapport,
donc absent du DENOMINATEUR. Une fonctionnalite entiere livree sans le moindre
test ne faisait donc pas bouger le taux affiche (revue mobile 2026-08, constat
C68). `test/coverage_barrel_test.dart` les charge donc tous ; ce script verifie
que ce barrel est complet, pour que sa suppression ou son oubli fasse echouer la
porte au lieu de faire remonter le taux en silence.
"""
import pathlib
import sys


def excluded(path: str) -> bool:
    return (
        "/api/generated/" in path
        or path.endswith(".g.dart")
        or path.endswith(".freezed.dart")
        or "l10n/app_localizations" in path
    )


BARREL = "test/coverage_barrel_test.dart"


def missing_from_barrel() -> list[str]:
    """Fichiers de `lib/` que le barrel de couverture n'importe pas.

    Un fichier de declarations pures (couleurs, routes, enum) n'a aucune ligne
    instrumentable et n'apparait donc jamais dans le rapport, meme charge : la
    completude se verifie sur le barrel, pas sur le lcov.
    """
    root = pathlib.Path(__file__).resolve().parent.parent
    wanted = {
        f"import 'package:coparentplanner/{p.relative_to(root / 'lib')}';"
        for p in (root / "lib").rglob("*.dart")
        if not excluded(str(p))
    }
    try:
        imported = set((root / BARREL).read_text().splitlines())
    except OSError:
        return sorted(wanted)

    return sorted(wanted - imported)


def main() -> int:
    lcov = sys.argv[1] if len(sys.argv) > 1 else "coverage/lcov.info"
    threshold = float(sys.argv[2]) if len(sys.argv) > 2 else 70.0

    try:
        lines = open(lcov).read().splitlines()
    except OSError as e:
        print(f"Rapport de couverture introuvable : {e}", file=sys.stderr)
        return 2

    cur = None
    found = hit = 0
    for ln in lines:
        if ln.startswith("SF:"):
            cur = ln[3:]
        elif ln.startswith("DA:") and cur and not excluded(cur):
            parts = ln[3:].split(",")
            found += 1
            if int(parts[1]) > 0:
                hit += 1

    missing = missing_from_barrel()
    if missing:
        print(
            f"ECHEC : ces fichiers de lib/ ne sont pas charges par {BARREL}, ils "
            "ne pesent donc pas au denominateur. Ajoutez-y ces lignes :",
            file=sys.stderr,
        )
        for line in missing:
            print(f"  {line}", file=sys.stderr)
        return 1

    pct = 100 * hit / found if found else 100.0
    print(
        f"Couverture mobile (lignes, hors genere) : {pct:.1f}% "
        f"({hit}/{found}) — seuil {threshold:.0f}%"
    )
    if pct + 1e-9 < threshold:
        print("ECHEC : couverture sous le seuil.", file=sys.stderr)
        return 1
    print("OK : seuil atteint.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
