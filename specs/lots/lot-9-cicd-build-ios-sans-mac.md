# Lot 9 — CI/CD & build iOS sans Mac

> Spécification détaillée d'implémentation, dérivée du cahier des charges `specs/cdc.md` (source de vérité) et des invariants verrouillés de `CLAUDE.md`. Référence cdc : §4.8 (Infra & déploiement), §4.9 (Tests & qualité), §8.10 (CI, référence Lot 0), §9 (découpage en lots).
>
> Ce lot **n'introduit aucune fonctionnalité produit visible par l'utilisateur final**. Il complète l'outillage CI posé au Lot 0 (§8.10 : *« build iOS/Android (option CI) : au moins compilation »*) pour couvrir la **compilation, la signature et la distribution de l'app mobile iOS**, sans qu'aucun poste de travail Mac ne soit nécessaire au quotidien. Il n'a donc **pas de section « Design system »** ni d'écran associé.

---

## 0. Objectif & périmètre

### 0.1 Objectif

Rendre la CI capable de produire un **build iOS signé** (simulateur ET appareil réel / TestFlight) de bout en bout, sans dépendre d'un Mac physique détenu par l'équipe, en s'appuyant sur un **runner macOS hébergé**. Le lot couvre :

1. Le **choix et la configuration du runner macOS** utilisé par la CI pour les jobs iOS — avec **deux implémentations maintenues en parallèle** (GitHub Actions et Codemagic, §2), afin de pouvoir basculer de l'une à l'autre sans réécrire la logique de build/signature.
2. La **gestion des certificats et profils de provisionnement** (signing) de façon automatisée, reproductible et **indépendante du runner CI** — sans stockage de secrets en clair dans le dépôt.
3. La **compilation Flutter → `.ipa`** en CI, avec upload TestFlight en option manuelle (pas d'auto-déploiement App Store sans validation humaine).
4. La **documentation** du dispositif dans `/infra/INSTALL.md` et `CLAUDE.md`, pour qu'un nouveau contributeur comprenne comment obtenir un build iOS sans Mac, quelle que soit la CI active.

> **Principe directeur** : la logique de build/signature ne vit **jamais** dans la configuration propre à une CI. Elle vit dans un **`Fastfile` unique, commun aux deux CI** (§2.4). GitHub Actions et Codemagic ne sont que deux « déclencheurs » différents qui appellent les mêmes lanes Fastlane (`fastlane ios build`, `fastlane ios release`). Changer de CI = changer de fichier de configuration CI (workflow YAML ou `codemagic.yaml`), **jamais** de logique métier de build.

### 0.2 Dans le périmètre

- **Deux pipelines CI maintenus en parallèle**, tous deux opérationnels et testés :
  - **GitHub Actions**, image `macos-latest` — cohérent avec le fait que la CI du Lot 0 (§8.10) tourne déjà sur GitHub Actions pour les jobs backend/web/Android ; aucun second système à apprendre pour l'équipe déjà en place.
  - **Codemagic** — spécialisé Flutter, UI dédiée mobile, utile en secours si les runners macOS GitHub sont indisponibles/en file d'attente, ou si l'équipe veut une gestion de build mobile plus visuelle.
  - Les deux sont **actifs simultanément par défaut** (l'un déclenché sur tag de release, l'autre disponible en `workflow_dispatch`/déclenchement manuel équivalent) ; **basculer le pipeline principal** ne demande qu'un changement de déclencheur, pas de réécriture (§2.4).
- **Gestion des identités de signature** via **Fastlane match**, **indépendante du runner CI** : un dépôt Git **privé et chiffré** dédié stocke les certificats et profils de provisionnement chiffrés (clé de chiffrement en secret, jamais commitée en clair) ; `match` les synchronise à l'identique sur un runner GitHub Actions ou sur un runner Codemagic, sans jamais exposer le `.p12`/`.mobileprovision` en clair dans les logs ou artefacts.
- **Logique de build centralisée dans un `Fastfile` unique** (§2.4), appelé à l'identique par les deux CI : checkout, setup Flutter, `pod install`, `fastlane match appstore` (ou `development` pour les builds de test), `flutter build ipa`, upload de l'`.ipa` en artefact, et optionnellement vers TestFlight via `fastlane pilot upload`.
- **Authentification App Store Connect sans compte Apple partagé** : clé API App Store Connect (`.p8`, issuer ID, key ID) en secrets — dupliquée dans les deux systèmes (secrets GitHub Actions **et** variables d'environnement Codemagic), jamais d'identifiant/mot de passe Apple personnel utilisé en CI.
- **Déclenchement** : job manuel (`workflow_dispatch` côté GitHub Actions, build manuel côté Codemagic) ou sur tag de release ; **pas d'upload TestFlight automatique sur chaque commit**, sur aucune des deux CI (évite la sur-sollicitation d'App Store Connect et les révisions accidentelles).
- **Documentation** : section dédiée dans `/infra/INSTALL.md` couvrant **les deux pipelines** (comment un contributeur sans Mac déclenche/récupère un build iOS via l'un ou l'autre) et mise à jour de `CLAUDE.md` (§ Outillage) une fois échafaudé.
- **Non-régression** des jobs CI existants (backend, web E2E, Android, `flutter test`) posés au Lot 0 — inchangés, toujours sur GitHub Actions.

### 0.3 Hors périmètre (explicitement)

- **Achat/gestion du compte Apple Developer Program** (99 $/an) : action administrative hors CI, à réaliser une fois par le porteur produit ; ce lot suppose le compte déjà créé et ses identifiants (clé API ASC) disponibles.
- **Publication effective sur l'App Store** (soumission à revue Apple) : reste une action **manuelle et volontaire**, jamais automatisée par la CI. Le lot livre le pipeline jusqu'à TestFlight (interne), pas jusqu'à la mise en production publique.
- **Notifications push (APNs)** : configuration des credentials APNs pour les notifications = **Lot 11 (push mobile, ex-Lot 10)** ; ce lot ne traite que la **compilation/signature/distribution**, pas le runtime push.
- **Web push / PWA build** : hors périmètre (pas de Mac requis pour le web).
- **Achat/gestion d'un Mac physique ou d'un abonnement MacStadium/MacinCloud** : le lot retient exclusivement des **runners CI hébergés à la demande** (GitHub Actions et Codemagic, §0.2) plutôt que la location d'un Mac distant permanent ; cette dernière reste une alternative possible mais non implémentée (elle nécessiterait une maintenance de poste que ce lot cherche justement à éviter).

---

## 1. Dépendances

### 1.1 Lots prérequis (avant)

| Lot | Pourquoi il est requis |
|---|---|
| **Lot 0 — Fondations** | Pose la CI de base (pipeline GitHub Actions, jobs backend/web/mobile §8.10), le projet Flutter (iOS + Android), `/infra/INSTALL.md`. Ce lot **étend** le job mobile existant plutôt que d'en créer un nouveau système. |

> Aucun autre lot fonctionnel (1 à 8) n'est requis : ce lot est purement outillage/infra et peut en théorie être livré dès que le Lot 0 a posé le projet Flutter. Il est positionné en **Lot 9** (avant Dépenses et Push mobile) pour que les lots v2 suivants bénéficient d'un pipeline de build iOS déjà opérationnel lors de leurs propres tests sur device réel.

### 1.2 Lots débloqués (après)

| Lot | Ce que le Lot 9 lui apporte |
|---|---|
| **Lot 10 — Dépenses (v2)** | Peut être testé sur un build iOS réel (TestFlight) dès sa livraison, sans dépendre d'un Mac local pour valider le rendu mobile. |
| **Lot 11 — Push mobile (v2)** | Bénéficie directement du pipeline de signature iOS pour tester les notifications APNs sur device réel (les notifications push ne fonctionnent pas sur simulateur iOS) — dépendance **pratique**, pas fonctionnelle : Lot 11 pourrait théoriquement être livré sans ce lot, mais sa validation serait alors impossible sans Mac. |

> Remarque de renumérotation : ce lot s'insère **avant** l'ancien Lot 9 (Dépenses), qui devient **Lot 10**, et l'ancien Lot 10 (Push mobile), qui devient **Lot 11**. Voir fichiers renommés `lot-10-depenses-v2.md` et `lot-11-push-mobile-v2.md`.

---

## 2. Architecture & décisions techniques

### 2.1 Pourquoi deux CI maintenues en parallèle plutôt qu'un Mac local/loué en permanence

| Option | Avantages | Inconvénients | Statut |
|---|---|---|---|
| **GitHub Actions `macos-latest`** | Intégré à la CI existante (mêmes secrets, mêmes workflows que backend/web/Android) ; aucune machine à maintenir ; facturé à la minute. | Coût/minute plus élevé que les runners Linux ; files d'attente possibles aux heures de pointe. | **Retenu — pipeline principal** |
| **Codemagic** | Spécialisé Flutter ; UI dédiée mobile ; secours immédiat si GitHub Actions est indisponible/en file d'attente. | Système CI séparé (compte/dashboard distinct) ; secrets à dupliquer. | **Retenu — pipeline de secours / alternatif** |
| **Mac loué à l'année (MacStadium/MacinCloud)** | Coût prévisible à volume élevé ; accès Mac complet si besoin de debug interactif. | Maintenance du poste (OS, Xcode, mises à jour) à la charge de l'équipe ; pas de scalabilité à la demande. | Non retenu |

**Décision de conception** : plutôt que de choisir une seule CI, ce lot maintient **les deux pipelines opérationnels en continu**, tous deux branchés sur le **même `Fastfile`** (§2.4) et le **même dépôt de certificats `match`** (§2.2). Ainsi :
- Si GitHub Actions a un incident ou une file d'attente longue sur les runners macOS, l'équipe déclenche le build sur Codemagic sans aucune préparation supplémentaire.
- Si l'équipe décide plus tard d'abandonner l'un des deux (ex. ne garder que Codemagic pour simplifier), il suffit de retirer le fichier de workflow correspondant — la logique de signature/build n'est pas dupliquée, donc rien à réécrire.
- Le coût de maintenance du doublon est faible : seuls les fichiers de **déclenchement** (`.github/workflows/build-ios.yml` et `codemagic.yaml`) diffèrent ; ils ne font chacun qu'une poignée d'étapes génériques (checkout, setup Flutter/Ruby, appel Fastlane).

### 2.2 Fastlane match — principe (commun aux deux CI)

`match` centralise les certificats/profils dans un **dépôt Git séparé et privé**, chiffrés avec une passphrase stockée en secret (`MATCH_PASSWORD`). À chaque run CI, **quel que soit le runner** :
1. Le runner clone le dépôt de certificats (accès via un token Git dédié, secret).
2. `match` déchiffre localement les certificats avec `MATCH_PASSWORD`, les installe dans le keychain temporaire du runner.
3. Le build est signé ; **le keychain temporaire est détruit en fin de job** (runner éphémère, aussi bien chez GitHub Actions que chez Codemagic).

Aucun certificat, profil ou mot de passe n'apparaît **jamais en clair** dans les logs, artefacts ou l'historique Git du dépôt applicatif principal — sur aucune des deux CI.

### 2.3 Alternative documentée (non implémentée)

Si l'équipe préfère éviter Fastlane match, l'**App Store Connect API Key** seule (sans gestion de certificats via match) permet à `fastlane` de générer/récupérer les profils à la volée (`get_certificates`/`get_provisioning_profile` avec API key) — plus simple à démarrer, mais moins reproductible en cas de run concurrent sur les deux CI simultanément. Non retenu par défaut car `match` est justement ce qui permet de garder la signature **identique et partagée** entre GitHub Actions et Codemagic.

### 2.4 Le `Fastfile` partagé — clé de l'interchangeabilité

Toute la logique métier de build vit dans un seul fichier, `/mobile/ios/fastlane/Fastfile`, avec deux lanes :

```ruby
# /mobile/ios/fastlane/Fastfile

platform :ios do
  desc "Compile un .ipa signé (readonly sur les certificats)"
  lane :build do
    match(type: "appstore", readonly: true)
    build_app(
      workspace: "Runner.xcworkspace",
      scheme: "Runner",
      export_method: "app-store"
    )
  end

  desc "Compile puis pousse sur TestFlight"
  lane :release do
    build
    upload_to_testflight(skip_waiting_for_build_processing: true)
  end
end
```

Ni le workflow GitHub Actions ni `codemagic.yaml` ne contiennent de logique de signature ou de build : ils se contentent d'installer les prérequis (Flutter, Ruby/Bundler, CocoaPods) puis d'appeler `bundle exec fastlane ios build` ou `bundle exec fastlane ios release`. C'est ce découplage qui permet de **changer de CI en changeant un seul fichier de déclenchement**, sans toucher à `Fastfile`, `Matchfile` ni à la gestion des secrets métier.

---

## 3. Pipeline CI détaillé

### 3.1 Implémentation GitHub Actions (`.github/workflows/build-ios.yml`)

```
Déclencheurs : workflow_dispatch (manuel) OU push sur tag `v*`
Runner : macos-latest

Étapes :
1. Checkout du dépôt applicatif (profondeur suffisante pour les tags).
2. Setup Flutter (version figée, cf. Lot 0) + `flutter pub get`.
3. Régénération/vérification du client Dart depuis l'OpenAPI (cohérence avec le job backend, cf. Lot 0 §3.9) — échec si drift.
4. `cd ios && pod install` (CocoaPods, dépendances natives Flutter iOS).
5. Installation Ruby/Bundler.
6. `bundle exec fastlane ios build` (build simple, artefact CI) OU `bundle exec fastlane ios release` (build + upload TestFlight), selon l'input du `workflow_dispatch`.
7. Upload de l'`.ipa` en artefact CI (téléchargeable, conservé N jours).
8. Nettoyage : keychain temporaire détruit par `match` en fin de run (runner éphémère).

RÉSULTAT : `.ipa` signé en artefact CI ; upload TestFlight seulement si la lane `release` a été explicitement choisie.
```

### 3.2 Implémentation Codemagic (`codemagic.yaml`, à la racine du dépôt)

```yaml
workflows:
  ios-build:
    name: iOS Build (secours / alternatif à GitHub Actions)
    max_build_duration: 60
    instance_type: mac_mini_m2
    environment:
      groups:
        - ios_signing        # regroupe MATCH_PASSWORD, MATCH_GIT_URL, ASC_KEY_*, APPLE_TEAM_ID
      vars:
        FLUTTER_VERSION: "<version figée, identique au Lot 0>"
    triggering:
      events: []              # déclenchement manuel uniquement depuis le dashboard Codemagic
    scripts:
      - name: Get Flutter dependencies
        script: flutter pub get
      - name: Regénérer/vérifier le client OpenAPI
        script: dart run build_runner build --delete-conflicting-outputs   # ou script équivalent au Lot 0 §3.9
      - name: Install CocoaPods
        script: cd ios && pod install
      - name: Build & sign via Fastlane (lane partagée)
        script: cd ios && bundle exec fastlane ios build
    artifacts:
      - build/ios/ipa/*.ipa
    publishing:
      # Étape "release" (upload TestFlight) déclenchée séparément via un second workflow
      # `ios-release`, qui appelle `bundle exec fastlane ios release` — jamais automatique.
      email:
        recipients: []
```

> Le workflow Codemagic **n'a aucune logique de signature propre** : il installe les mêmes prérequis que GitHub Actions puis appelle la **même lane Fastlane** (§2.4). Un second workflow `ios-release` (non détaillé ici, symétrique) appelle `fastlane ios release` pour l'upload TestFlight, exactement comme l'option `release` du workflow GitHub Actions.

### 3.3 Basculer d'une CI à l'autre

| Scénario | Action |
|---|---|
| GitHub Actions indisponible ponctuellement | Déclencher `ios-build`/`ios-release` depuis le dashboard Codemagic — aucune configuration préalable requise, les secrets sont déjà synchronisés (§4). |
| Décision définitive de n'utiliser que Codemagic | Supprimer `.github/workflows/build-ios.yml` ; conserver `codemagic.yaml`, `Fastfile`, `Matchfile` inchangés. |
| Décision définitive de n'utiliser que GitHub Actions | Supprimer `codemagic.yaml` (ou désactiver le projet côté dashboard Codemagic) ; conserver le reste inchangé. |

Dans les trois cas, **aucune modification de `Fastfile` ni du dépôt de certificats `match`** n'est nécessaire : c'est précisément l'objet de l'abstraction posée en §2.4.

### 3.4 Intégration avec la CI existante (Lot 0 §8.10)

Le job GitHub Actions `build-ios` **s'ajoute** aux jobs A (backend), B (web E2E) et C (mobile — `flutter test`/`dart analyze`) déjà définis au Lot 0, sans les remplacer : le job C continue de tourner sur runner Linux pour les tests unitaires/widgets (plus rapides et moins coûteux) ; seule la **compilation signée** nécessite un runner macOS (GitHub Actions ou Codemagic). Le déclenchement de `build-ios`/`ios-build` est conditionné à la réussite du job C (pas de build iOS signé si les tests Flutter échouent).

---

## 4. Gestion des secrets

Les mêmes secrets doivent exister **dans les deux systèmes CI**, sous des noms équivalents (GitHub Actions secrets / groupe de variables d'environnement Codemagic `ios_signing`), puisqu'ils pointent vers le **même** dépôt `match` et le **même** compte App Store Connect :

| Secret | Contenu | Nom GitHub Actions | Nom Codemagic (groupe `ios_signing`) |
|---|---|---|---|
| URL du dépôt de certificats | `match` | `MATCH_GIT_URL` | `MATCH_GIT_URL` |
| Passphrase de déchiffrement | `match` | `MATCH_PASSWORD` | `MATCH_PASSWORD` |
| Token d'accès au dépôt de certificats (lecture seule) | `match` | `MATCH_GIT_TOKEN` | `MATCH_GIT_TOKEN` |
| Clé API App Store Connect | Upload TestFlight | `ASC_KEY_ID`, `ASC_ISSUER_ID`, `ASC_KEY_CONTENT` | `ASC_KEY_ID`, `ASC_ISSUER_ID`, `ASC_KEY_CONTENT` |
| Identifiant d'équipe Apple | Signature | `APPLE_TEAM_ID` | `APPLE_TEAM_ID` |

**Règles** (cohérentes avec les invariants transverses du projet) :
- Aucun secret n'est jamais commité en clair, ni dans le dépôt applicatif, ni dans le dépôt de certificats (qui est lui-même chiffré par `match`).
- Les secrets sont accessibles uniquement aux workflows du dépôt (pas aux forks / pull requests externes), sur les deux CI.
- **Rotation synchronisée** : toute rotation de `MATCH_PASSWORD` ou de la clé API App Store Connect doit être répercutée **dans les deux systèmes le même jour** (documenté dans `/infra/INSTALL.md`), sous peine de désynchronisation silencieuse (l'un des deux pipelines cesserait de fonctionner sans erreur explicite avant le prochain run).

---

## 5. Modèle de données & API

**Non applicable.** Ce lot est un lot d'infrastructure/outillage CI ; il n'introduit aucune entité métier, aucune migration, aucun endpoint API, et ne touche pas au registre des données personnelles (aucune donnée personnelle utilisateur n'est traitée par le pipeline de build).

---

## 6. Invariants & règles

1. **Aucun secret en clair** : certificats, profils, clés API — toujours via secrets CI chiffrés, jamais dans le code ou les logs.
2. **Pas de déploiement App Store automatique** : seule la mise à disposition TestFlight (interne) peut être déclenchée en CI, sur action explicite ; la soumission à revue Apple reste manuelle.
3. **CI readonly sur les certificats** : le job `build-ios` utilise `match ... --readonly` ; la génération/révocation de certificats reste une opération manuelle exécutée localement par un mainteneur habilité (documentée, pas automatisée en CI).
4. **Non-régression des jobs existants** : les jobs backend/web/Android du Lot 0 restent verts ; `build-ios` est additif.
5. **Architecture verrouillée** : ce lot ne remet en cause ni Flutter, ni le choix de CI principale pour le reste du projet (GitHub Actions), ni l'absence de framework JS côté web. Il étend la couverture CI mobile iOS via **deux implémentations interchangeables** (GitHub Actions + Codemagic) qui partagent strictement la même logique de build/signature (`Fastfile`, `Matchfile`) — aucune des deux ne doit contenir de logique métier propre.
6. **`/infra/INSTALL.md` à jour** : toute nouvelle dépendance (Fastlane, CocoaPods, Ruby/Bundler, secrets requis) documentée avec version et procédure d'obtention.
7. **Traçabilité** : chaque build `.ipa` produit en CI est associé au commit SHA et au tag ayant déclenché le build (nommage d'artefact incluant ces informations).

---

## 7. Definition of Done (DoD)

**Tests / validation**
- [ ] Pipeline **GitHub Actions** déclenché manuellement produit un `.ipa` signé, téléchargeable en artefact CI, sans intervention d'un Mac local.
- [ ] Pipeline **Codemagic** déclenché manuellement produit un `.ipa` signé équivalent, en utilisant les mêmes certificats `match` — **preuve d'interchangeabilité** (les deux `.ipa` sont installables sur le même device de test).
- [ ] Chaque pipeline échoue proprement (message clair) si un secret requis est absent — pas de succès silencieux avec build non signé.
- [ ] Upload TestFlight testé au moins une fois de bout en bout **depuis chacune des deux CI** (build installable via l'app TestFlight sur un device réel).
- [ ] Non-régression vérifiée : jobs backend/web E2E/Android/`flutter test` toujours verts après ajout du job `build-ios`.

**Livrables techniques**
- [ ] Dépôt Git privé de certificats créé, initialisé via `fastlane match init` + `match appstore` (génération initiale, faite une fois, localement ou via un job CI dédié à exécution manuelle unique).
- [ ] `Fastfile`/`Matchfile`/`ExportOptions.plist` commités dans `/mobile/ios/`, **partagés sans duplication** entre les deux CI (hors secrets).
- [ ] Workflow GitHub Actions (`.github/workflows/build-ios.yml`) **et** configuration Codemagic (`codemagic.yaml`) commités et documentés.
- [ ] `/infra/INSTALL.md` mis à jour : section « Build iOS sans Mac » couvrant **les deux pipelines** (prérequis, secrets à configurer de chaque côté, procédure de déclenchement pour chacun, procédure de rotation synchronisée de `MATCH_PASSWORD`).
- [ ] `CLAUDE.md` (§ Outillage prévu) mis à jour avec les commandes réelles (cohérent avec l'exigence déjà posée par le Lot 0, §8.11).

**Qualité & conformité**
- [ ] Aucun secret n'apparaît dans les logs CI, sur aucune des deux plateformes (vérifié manuellement sur un run complet de chaque côté).
- [ ] Revue de sécurité rapide : accès au dépôt de certificats limité aux mainteneurs, secrets non exposés aux PR de forks externes (GitHub Actions) ni aux builds déclenchés par des contributeurs externes (Codemagic).
