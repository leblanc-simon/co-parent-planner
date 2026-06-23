# contracts/

Contrats d'interface partagés entre le backend et les clients.

## `openapi.json`

Schéma **OpenAPI** exporté par le backend Symfony / API Platform — **source de vérité des
types** pour le client mobile Dart (cf. cdc §4.1, §3.9 du Lot 0).

Régénération (depuis `backend/`) :

```bash
php bin/console api:openapi:export --output=../contracts/openapi.json
```

La CI échoue si le client Dart généré dérive de ce schéma (drift détecté).
