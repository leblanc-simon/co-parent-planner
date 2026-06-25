# contracts/

Contrats d'interface partagés entre le backend et les clients.

## `openapi.json`

Schéma **OpenAPI** exporté par le backend Symfony / API Platform — **source de vérité des
types** pour le client mobile Dart (cf. cdc §4.1, §3.9 du Lot 0).

Régénération (depuis `backend/`) :

```bash
php bin/console api:openapi:export --output=../contracts/openapi.json
# ou via le binaire symfony : symfony console api:openapi:export --output=../contracts/openapi.json
```

## Client mobile Dart

Le client Flutter est **généré** depuis ce schéma par [`swagger_parser`](https://pub.dev/packages/swagger_parser)
(**pur Dart**, sans openapi-generator/JVM) → clients `retrofit` sur `dio` + modèles `json_serializable`,
dans `mobile/lib/api/generated/` (commité, jamais édité à la main). Depuis `mobile/` :

```bash
dart run swagger_parser
dart run build_runner build --delete-conflicting-outputs --force-jit
```

La CI échoue si le client Dart généré dérive de ce schéma : `git diff --exit-code lib/api/generated/`
après régénération (drift détecté). La régénération est idempotente.
