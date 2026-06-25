#!/usr/bin/env python3
"""Garde-fou de couverture mobile (decision D8 : >= 70 % de lignes).

Parse coverage/lcov.info en EXCLUANT le code genere (client OpenAPI, *.g.dart,
l10n). Usage : python3 tool/check_coverage.py [lcov.info] [seuil].
"""
import sys


def excluded(path: str) -> bool:
    return (
        "/api/generated/" in path
        or path.endswith(".g.dart")
        or path.endswith(".freezed.dart")
        or "l10n/app_localizations" in path
    )


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
