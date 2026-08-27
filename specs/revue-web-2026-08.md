# Revue web (API + front) — 2026-08-27

> Revue de code **et** tests fonctionnels réels de la partie web, menés sur la stack locale
> (`symfony serve` + PostgreSQL + Mailpit + hub Mercure) : parcours dans Chrome via l'extension, puis
> vérification en HTTP/SQL de chaque constat. Complète `specs/revue-web-desktop-2026-07.md`, dont les
> 7 constats encore ouverts (6.8, 6.10, 6.6, 6.1, 6.5, 6.4, 6.3) ne sont **pas** repris ici.
>
> **Tout constat listé ci-dessous a été reproduit**, commande ou manipulation à l'appui. Les
> hypothèses non confirmées sont regroupées en fin de document.

## État des corrections — 2026-08-27

**Les 22 constats (C1 → C22) sont corrigés**, chacun couvert par un test de non-régression
(`backend/tests/Functional/Web/WebReviewFixesTest.php` pour le front, `ApiReviewFixesTest.php` pour
l'API) et revérifié dans Chrome sur la stack locale. Portes : backend **371 tests / 3074 assertions**,
PHPStan niveau 8 à 0, cs-fixer 0, Twig 35 ; mobile **180 tests**, `dart analyze` 0 ; OpenAPI ré-exporté
et client Dart régénéré. Migration fournie (`Version20260827204815`, périmètre des index d'idempotence).

Deux corrections **diffèrent de celle proposée par la revue** :

- **C2** — ajouter l'attribut `private` à `<turbo-stream-source>` **ne suffisait pas** : cet élément est
  celui de Turbo, pas de UX Turbo, et il ouvre son flux par `new EventSource(src)` — sans
  `withCredentials`, en ignorant purement `private`. Le correctif réel est de rendre l'élément
  `<turbo-mercure-stream-source private>` de UX Turbo (déjà chargé via `assets/controllers.json`).
  Vérifié en direct : une mise à jour publiée en `private` sur le topic `custody` est désormais reçue
  et rendue par la page (`turbo:before-stream-render` déclenché), là où elle n'arrivait jamais.
- **C1** — le pare-feu `main` passe en `stateless: true` (première option de la revue), l'invalidation
  explicite de session étant conservée en ceinture-bretelles pour les navigateurs porteurs d'un
  `PHPSESSID` antérieur au correctif. La protection CSRF du projet étant déjà sans session
  (`csrf.yaml` : `stateless_token_ids`), ce passage n'a coûté aucune fonctionnalité.

Corrections notables au-delà du strict énoncé, parce que le même défaut existait ailleurs :
**C6** (bornes également posées sur le corps d'un échange et les libellés d'options),
**C10** (les trois périmètres fautifs — `events`, `exchanges`, `messages`),
**C12** (`Sequentially` sur tous les champs concernés + messages métier au catalogue, verrouillés par
`TranslationCatalogTest`), **C20** (`version` exposée aussi sur les échanges),
**C11** (indicateur d'échec du temps réel : bandeau « Mises à jour en direct indisponibles »,
`assets/controllers/realtime_controller.js`).

Deux effets de bord ont été traités dans la foulée, parce que le correctif de C2 les a révélés :

- la suite **E2E ne démarrait aucun hub Mercure** : elle validait donc un front dégradé (abonnement
  en échec). Le hub est maintenant lancé par `web-e2e/scripts/e2e-local.sh` **et** par le job CI ;
- les **31 `waitForLoadState('networkidle')`** de la suite ne peuvent pas aboutir face à un flux SSE
  permanent. Remplacés par des attentes déterministes (`web-e2e/helpers/page.ts` : fin de chargement,
  ou confirmation de succès pour une mutation) — au passage, la suite passe de 4 min 30 à 42 s.
  Un test qui **exerce** réellement une mise à jour poussée reste à écrire.

Le **point d'attention produit** en fin de document est **tranché (PO, 2026-08-27) : ces champs
seront chiffrés**, dans un second temps. La décision est consignée au contrat (`specs/cdc.md` §5.2,
les trois champs passent 🔒) et au registre (`backend/docs/registre-donnees.md`, sensibilité « à
chiffrer » tant que ce n'est pas livré) ; elle **révise la décision §5.4 #4**. Rien n'est chiffré à
ce jour : la base reste lisible pour ces trois colonnes.

## Méthode

Jeu d'essai créé de bout en bout par l'interface : compte `revue.a@…` (Alice Dupont, admin), famille
« Famille Dupont » (séparée), enfant Léa, une note, invitation puis inscription du second parent
`revue.b@…` (Bruno Martin), rythme « une semaine sur deux » ancré au 24/08/2026, série d'événements
hebdomadaire, et un beau-parent `revue.c@…` (droits `agenda:view`, `exchanges:view`, **ni** `custody`
**ni** `children_notes`) servant de sonde d'autorisation.

Deux sessions simultanées dans le même Chrome, en exploitant le fait que les cookies sont liés à
l'hôte : parent A sur `127.0.0.1:8000`, parent B sur `localhost:8000`.

---

## P0 — Bloquant

### C1 — La déconnexion n'invalide pas la session : accès complet conservé après logout

`backend/src/Controller/SecurityController.php:68-88` · `backend/config/packages/security.yaml`
(pare-feu `main`).

Le pare-feu `main` n'est pas `stateless` : Symfony persiste le token d'authentification dans la
session PHP. `logout()` révoque le refresh token et efface les cookies `access_token` /
`refresh_token`, mais **n'invalide jamais la session**. Le cookie `PHPSESSID` survit et continue
d'authentifier tout le front web.

```
POST /api/auth/login              -> 200   (pose access_token + refresh_token)
POST /logout                      -> 302 /login   (efface les deux cookies)
GET  /children  (PHPSESSID seul)  -> 200 + contenu complet : prénom de l'enfant, école, note « Doudou… »
GET  /settings /garde /agenda /echanges (PHPSESSID seul) -> 200
GET  /api/me                      -> 401   (l'API, elle, est bien déconnectée)
Témoin : /children avec session vierge -> 302 /login
```

Trois conséquences, toutes observées :

1. **Confidentialité** — sur un appareil partagé (scénario natif du produit : co-parents, ordinateur
   familial, compte enfant), la personne suivante conserve l'accès à tous les écrans, y compris les
   notes sur les enfants — la donnée que le projet chiffre précisément au repos.
2. **Impossible de se déconnecter** — `/logout` est sans effet et `/login` redirige vers l'accueil
   tant que la session vit. Le bouton « Se déconnecter et continuer » de l'écran « Compte différent »
   ne mène donc nulle part : **on ne peut pas accepter une invitation depuis un navigateur déjà
   connecté à un autre compte**.
3. **Session zombie** — le front reste affiché comme connecté mais toutes les mutations répondent
   401 ; `ApiController._send()` redirige alors vers `/login?expired=1`, qui renvoie à l'accueil.
   L'utilisateur est baladé sans le moindre message. C'est ce qui s'est produit lors du test du
   formulaire « Rythme de garde » : clic sur « Enregistrer », retour à l'accueil, rien d'enregistré.

**Correction** : passer le pare-feu `main` en `stateless: true` (cohérent avec l'authentification
JWT-en-cookie déjà en place), ou invalider explicitement la session dans `logout()`.

---

## P1 — Fonctionnalité cassée

### C2 — Le temps réel web ne fonctionne dans aucun écran

`backend/templates/app/{garde,agenda,echanges,children}.html.twig` ·
`vendor/symfony/ux-turbo/assets/dist/mercure_stream_source_element.js:6`.

Le composant `<turbo-stream-source>` de UX Turbo n'envoie les cookies au hub que si l'attribut
`private` est présent :

```js
this.es = new EventSource(src, { withCredentials: this.hasAttribute("private") });
```

Or **aucune** des quatre balises du projet ne porte cet attribut :

```
templates/app/echanges.html.twig:21   <turbo-stream-source src="{{ mercureHub }}?topic=…">
templates/app/garde.html.twig:36      <turbo-stream-source src="{{ mercureHub }}?topic=…">
templates/app/agenda.html.twig:26     <turbo-stream-source src="{{ mercureHub }}?topic=…">
templates/app/children.html.twig:133  <turbo-stream-source src="{{ mercureHub }}?topic=…">
```

…alors que **les quatre publishers publient en `private: true`** (`CustodyPublisher.php:32`,
`ExchangePublisher.php:51`, `NotePublisher.php:38`, `AgendaPublisher.php:32`). Une mise à jour privée
n'est délivrée qu'à un abonné autorisé ; le cookie `mercureAuthorization` n'étant jamais transmis,
l'abonnement est anonyme et **ne reçoit jamais rien**.

Conséquence : le temps réel — contractualisé au Lot 6 (échanges) et utilisé pour la garde, l'agenda
et les notes — est inopérant sur tout le web. Le défaut est passé inaperçu parce que la suite E2E ne
couvre pas le temps réel (`PROGRESS.md` l'admet : « hub Mercure absent de la stack `e2e-local.sh` »)
et que la stack de développement ne permet pas de l'exercer (voir C11).

**Correction** : ajouter l'attribut `private` aux quatre balises.

### C3 — Contrat OpenAPI : aucune opération ne déclare ses paramètres de requête

`contracts/openapi.json`.

```
Opérations sans aucun paramètre de requête déclaré : 70 / 70
```

Aucun `from`, `to`, `scope`, `overlay`, `month` n'apparaît dans le contrat, alors que ces paramètres
sont **structurants** :

- `GET /api/families/{id}/events?from=&to=` — indispensable pour afficher l'agenda ;
- `PATCH|DELETE /api/events/{id}?scope=occurrence|series` (+ `recurrenceDate` / `from`) — seule façon
  de distinguer « cette occurrence » de « toute la série » ;
- `GET /api/families/{id}/custody/days?from=&to=` ; `?overlay=` pour les familles liées.

Or `/contracts/openapi.json` est la **source du client Dart mobile** (invariant du projet). Le défaut
a déjà coûté : `PROGRESS.md` documente un contournement en place — « événements projetés via appel
dio brut car `?overlay=` non exposé par le client généré ». Le contrat ment aussi aux futurs
intégrateurs et à la documentation publique `/api/docs`.

### C4 — Le service worker met en cache les pages privées et les sert après déconnexion

`backend/templates/pwa/sw.js.twig` (`navigationStrategy`, cache `cpp-pages-*`).

Relevé dans le navigateur, cache inspecté après le parcours :

```
cpp-pages-… : /  /children  /garde  /settings  /onboarding  /login  /invitations/accept
  /          -> « Alice Dupont », « Famille Dupont »
  /children  -> « Doudou lapin indispensable pour dormir… »   (contenu d'une note)
  /garde, /settings -> identités
```

Le cache n'est purgé ni à la déconnexion ni au changement de compte : il n'est cloisonné que par
famille (`familyKey()`), jamais par utilisateur. Hors ligne, le porteur suivant de l'appareil obtient
les écrans du compte précédent — notes comprises. `navigationStrategy` met par ailleurs en cache
toute réponse `resp.ok`, y compris la page de login obtenue après une redirection 302 (`fetch` suit
la redirection), ce qui pollue l'entrée de l'écran demandé.

**Correction** : ne pas mettre en cache une réponse `redirected`, et vider `cpp-pages-*` au logout et
au changement d'utilisateur.

### C5 — « Toute la série » ignore l'occurrence de scission si elle a déjà été modifiée

`backend/src/State/Agenda/UpdateEventProcessor.php` (scission de série).

Série hebdomadaire « Judo », 6 occurrences à partir du jeudi 03/09/2026. Test :

```
1. PATCH ?scope=occurrence  recurrenceDate=2026-09-17  title="Judo (gala)"   -> 200
2. PATCH ?scope=series      recurrenceDate=2026-09-17  title="Judo avance"   -> 200

Résultat :
   03/09 Judo          10/09 Judo
   17/09 Judo (gala)   <-- occurrence visée par « toute la série », NON modifiée
   24/09 Judo avance   01/10 Judo avance   08/10 Judo avance
```

L'utilisateur ouvre l'occurrence du 17/09, choisit « toute la série (à partir de cette date) », et
c'est précisément l'occurrence qu'il regardait qui garde l'ancien titre, parce qu'elle porte un
override antérieur. Incohérent et silencieux.

---

## P2 — Comportement incorrect dans un cas réaliste

### C6 — Validations manquantes : 500 au lieu de 422, avec fuite du schéma SQL

```
POST /api/families/{id}/exchanges   title = 300 caractères
  -> 500 « SQLSTATE[22001]: String data, right truncated: value too long for type character varying(200) »

PUT /api/me/notification-prefs      locale = "<script>zz</script>"
  -> 500 « SQLSTATE[22001]: … value too long for type character varying(16) »
```

Aucune contrainte applicative ne borne ces champs : l'erreur remonte de PostgreSQL. Deux défauts —
le code HTTP (une entrée invalide doit donner 422, pas 500) et le fait que la réponse expose les
types et longueurs de colonnes. Le reste de l'API valide pourtant très bien (voir « Conformes »).

### C7 — `/garde` divulgue le rythme et l'heure d'échange à un membre sans droit `custody`

`backend/templates/app/garde.html.twig:19` · `backend/src/Controller/GardeController.php:79,125`.

Le sous-titre est rendu dans le `<header>`, **avant** le branchement sur le droit. Testé avec le
beau-parent C (aucun droit `custody`) :

```
API : GET /api/families/{id}/custody/pattern  -> 403
WEB : GET /garde                              -> 200
      « Planning de garde · Une semaine sur deux · échange à 19:00
        Vous n'avez pas accès au planning de garde de cette famille. »
```

Le web est donc plus permissif que l'API sur le même domaine de droit, ce qui contredit l'invariant
« l'autorisation se vérifie sur chaque ressource ». Les écrans Agenda et Échanges n'ont pas ce défaut.

### C8 — Message d'autorisation non interpolé, visible par l'utilisateur

```
GET /api/families/{id}/custody/pattern  (membre sans droit)
  -> 403 {"detail":"Droit {right} requis."}
```

Le paramètre ICU `{right}` n'est jamais substitué. Ce message est celui de **tout** refus
d'autorisation de l'API, donc affichable tel quel par le front.

### C9 — `recurrenceDate` n'accepte pas le format que l'API elle-même renvoie

L'API expose l'occurrence comme `"startsAt": "2026-09-17T17:00:00+02:00"`, mais n'accepte en entrée
que la date seule :

```
recurrenceDate = 2026-09-17                 -> 200
recurrenceDate = 2026-09-17T17:00:00+02:00  -> 422 « La date ne correspond à aucune occurrence de la série. »
recurrenceDate = 2026-09-17T15:00:00Z       -> 422  (idem)
```

Le message est **trompeur** : il affirme que l'occurrence n'existe pas alors que seul le format est
en cause. Le contrat, lui, annonce un simple `string` sans `format` (voir C3) : un client qui renvoie
l'instant reçu tombe dans une impasse incompréhensible.

### C10 — La clé d'idempotence est scopée à la famille, pas à l'utilisateur

Deux membres de la même famille, **même** `Idempotency-Key`, contenus différents :

```
A : POST /events  Idempotency-Key: K  « Evenement de A »          -> 201  (créé)
B : POST /events  Idempotency-Key: K  « Evenement de B »          -> 201  mais renvoie « Evenement de A »
```

B reçoit un 201 et la ressource **d'un autre utilisateur** ; sa propre création est silencieusement
perdue. À noter, le mécanisme est par ailleurs correct : A rejouant **sa** clé avec un contenu
différent obtient bien `409 Conflit` — la comparaison du payload existe. Seul le périmètre de la clé
est trop large.

### C11 — Temps réel inexerçable en développement : `example.com` par défaut, hub en HTTPS forcé

`backend/.env:112-114` · `backend/compose.yaml` (service `mercure`).

Hors stack Docker — c'est-à-dire dans le mode documenté par `CLAUDE.md` (`symfony serve`) — les
variables valent encore celles du recipe Symfony. Le navigateur émet alors en boucle, vers un
**domaine tiers**, des requêtes contenant l'identifiant de famille :

```
GET https://example.com/.well-known/mercure?topic=/families/01a03fe6-…/custody  -> 503
```

Et si l'on démarre le hub fourni par `backend/compose.yaml`, il force HTTPS et redirige vers un port
fermé :

```
GET http://127.0.0.1:32768/.well-known/mercure?topic=…
  -> 308 Location: https://127.0.0.1/.well-known/mercure?topic=…   (rien n'écoute en 443)
```

Le temps réel n'est donc validable ni en développement, ni en E2E — ce qui explique que C2 soit
passé inaperçu. Aucun indicateur d'échec n'est affiché à l'utilisateur : la page paraît simplement
figée.

### C12 — Messages de validation bruts : nom technique du champ et doublons

Observé tel quel à l'écran, sur les formulaires qui n'héritent pas du socle `ApiController` :

```
/register     password: Cette chaîne est trop courte. Elle doit avoir au minimum 12 caractères.
/verify-email token: Cette valeur ne doit pas être vide.
/onboarding   name: Cette valeur ne doit pas être vide. name: Cette chaîne est trop courte. Elle doit
              avoir au minimum 1 caractère.
```

Trois défauts cumulés : le nom technique fuit dans l'interface, le libellé est le message générique
du validateur (pas une chaîne métier du catalogue), et deux violations contradictoires s'affichent
pour une seule cause. `ApiController._errorMessage()` (`backend/assets/api_base.js:126`) fait déjà le
bon travail — préférer `violations[].message` au `detail` RFC 7807 — mais les écrans d'authentification
ne l'utilisent pas. Sur `/verify-email`, le message d'erreur s'affiche en plus **sous** le texte
« Validation de votre adresse en cours… », qui reste visible.

### C13 — « Rythme de garde » : les deux parents pré-remplis à l'identique, échec silencieux

`backend/templates/app/settings.html.twig:170`.

Les sélecteurs « Parent A » et « Parent B » sont tous deux pré-remplis avec **le même** membre. En
l'état, l'enregistrement ne peut qu'échouer : l'API répond correctement
`422 — « Les parents A et B doivent être deux membres parents distincts de la famille. »`, mais
l'utilisateur ne voit aucun message.

**Correction** : pré-sélectionner deux membres distincts et neutraliser dans B l'option choisie en A.

### C14 — Les heures saisies sont interprétées dans le fuseau du navigateur, pas dans celui de la famille

`backend/assets/controllers/agenda_controller.js:10,146`.

```js
static values = { timezone: String };          // fournie par le template…
return localValue ? new Date(localValue).toISOString() : null;   // …et jamais utilisée
```

`new Date('2026-09-07T10:00')` interprète l'heure murale dans le fuseau **du navigateur**. Un parent
en déplacement hors d'Europe/Paris qui saisit « 10:00 » enregistre 10:00 de son fuseau courant, soit
une autre heure pour la famille. La valeur `timezone` est passée au contrôleur mais n'est lue nulle
part.

---

## P3 — Défauts mineurs mais réels

### C15 — `/garde` délivre un cookie Mercure `custody` sans vérifier le droit

Comparaison directe pour le beau-parent C (aucun droit `custody`) :

```
/api/realtime/token  -> topics : agenda, exchanges          (correct, droit respecté)
page /garde          -> cookie mercureAuthorization : ['/families/{id}/custody']   (accordé à tort)
/children            -> aucun cookie                        (correct : droit children_notes absent)
```

Portée limitée : ce topic ne transporte qu'un signal `<turbo-stream action="refresh">`, sans donnée
métier — seule l'information « la garde vient de changer » fuit. Le topic `notes`, lui, publie du
HTML réel et n'est correctement pas accordé.

### C16 — Le JWT d'abonnement Mercure n'a pas d'expiration

Claims du cookie `mercureAuthorization` :

```json
{"mercure":{"publish":[],"subscribe":["/families/…/custody"]}}
```

Ni `exp`, ni `iat`. La capacité d'écoute est donc perpétuelle et non révocable : elle survit au
retrait du membre de la famille et à tout changement de droits.

### C17 — `/settings` : deux entrées de navigation vers des ancres inexistantes

Tant que la famille n'a pas de rythme de garde, les sections « Exceptions » (`#sec-exceptions`) et
« Vacances » (`#sec-holidays`) ne sont pas rendues, alors que la navigation latérale continue de les
proposer. Vérifié : `document.querySelector('#sec-exceptions') === null`. Cliquer ne produit rien.

### C18 — E-mails : la partie `text/plain` ne contient aucun lien d'action

`backend/templates/emails/*`. La partie texte est le HTML dépouillé de ses balises : le bouton
disparaît sans laisser d'URL, et les entités restent encodées.

```
Bienvenue sur CoParentPlanner
Pour activer votre compte, confirmez votre adresse e-mail en cliquant sur le bouton ci-dessous.
    Vérifier mon e-mail                         <-- aucun lien
Si vous n&#039;êtes pas à l'origine de cette inscription…   <-- entité non décodée
```

Identique sur l'e-mail d'invitation. Un client en mode texte seul rend l'e-mail inutilisable.

### C19 — « Statistiques du mois » calculées sur les seuls jours matérialisés

`/garde`, août 2026 avec un rythme ancré au 24/08 : le moteur ne génère pas le passé, donc 6 jours
seulement existent. L'encart affiche pourtant « Alice Dupont · 66,7 % » / « Bruno Martin · 33,3 % »
sous le titre « STATISTIQUES DU MOIS », sans dire que le mois n'est couvert qu'à 6/31. Les mois
entièrement générés sont justes (septembre : 14/16).

### C20 — Le verrou optimiste est inutilisable : `version` n'est jamais exposé en lecture

`PATCH /api/events/{id}` accepte `expectedVersion`, mais aucune lecture ne renvoie la version :

```
champs d'une occurrence : allDay, category, childIds, endsAt, id, isOverride, isRecurring,
                          recurrenceDate, responsibleMembershipId, seriesId, startsAt, title, wholeFamily
```

Un client ne peut donc jamais renseigner `expectedVersion` correctement : la protection contre
l'écriture concurrente existe côté serveur mais reste hors de portée des clients.

### C21 — i18n : pluriel bricolé au lieu d'un pluriel ICU

`/settings`, section Membres : « 1 personne(s) dans la famille. » — l'invariant impose ICU
(`{count, plural, one {# personne} other {# personnes}}`).

### C22 — `POST /logout` sans protection CSRF

Le formulaire ne porte aucun jeton (`hasCsrf: false`). Un site tiers peut donc forcer la déconnexion.
Impact faible et, en l'état, largement théorique — la déconnexion ne déconnecte pas (C1).

---

## Points vérifiés conformes

- **Autorisations de l'API** — sonde « beau-parent » (`agenda:view`, `exchanges:view`) :

  ```
  POST /events (agenda=view)                     -> 403
  GET  /children/{id}/notes (children_notes=none)-> 403
  POST /invitations (non admin)                  -> 403
  PUT  /custody/pattern (custody=none)           -> 403
  GET  events d'une famille non membre           -> 403
  PATCH self isAdmin=false (dernier admin)       -> 409 « Impossible de retirer ou rétrograder le
                                                    dernier administrateur de la famille. »
  ```

- **Chiffrement au repos** — `users.full_name`, `notes.text`, `messages.text`, `invitations.email`
  stockés en `enc:v1:…`, conformément à la liste 🔒 du cdc §5.2.
- **Export RGPD** — `POST /api/me/export` → 202 puis ZIP `export.json` versionné
  (`coparentplanner-rgpd-export/v1`), données correctement déchiffrées ; lien signé **et**
  authentification exigée (401 sans session, 401 si le hash est altéré).
- **Anti-énumération** — inscription (« Si cette adresse est disponible… ») et connexion
  (« Identifiants invalides. ») ne distinguent pas compte existant et inexistant.
- **Jeton de vérification à usage unique** — le rejeu du lien donne « Lien invalide ou expiré ».
- **Pas d'open redirect** — après logout, `next` n'est honoré que s'il commence par
  `/invitations/accept` (`SecurityController.php:80`).
- **Invitation adressée à un autre compte** — écran « Compte différent » explicite (le bouton est
  cassé par C1).
- **Moteur de garde** — alternance hebdomadaire correcte, bascule le dimanche (jour d'échange),
  marqueur textuel A/B en plus de la couleur, légende nominative, statistiques justes sur un mois
  complet. Récurrence agenda : `count: 6` respecté, occurrences hebdomadaires exactes.
- **Robustesse** — `/garde?month=pas-une-date` et `/children?child=<uuid inconnu>` répondent 200 sans
  planter.
- **Garde-fou « famille à un seul parent »** — `/garde` explique et renvoie vers les membres, puis
  vers le rythme dès que le second parent a rejoint la famille.
- **Bandeau anti-données-de-santé** présent sur la fiche enfant ; informations pratiques en paires
  libres ; onglet Dépenses absent (v1).
- **Notifications e-mail** — le corps est une chaîne traduite ne transportant qu'un titre et un lien :
  aucun champ 🔒 (`Note.text`, `Message.text`) ne transite par la file Messenger.

## Point d'attention produit (arbitrage PO, pas un défaut d'implémentation)

> **Arbitrage rendu le 2026-08-27 : on chiffre. Implémentation dans un second temps** — le cdc §5.2
> marque désormais les trois champs 🔒 et le registre porte la mention « à chiffrer ».

`children.first_name`, `children.school_label` et `children.info` (champ libre) étaient stockés **en
clair** — conforme au cdc §5.2 **d'alors**, qui ne les marquait pas 🔒. Mais l'objectif énoncé dans
`CLAUDE.md` (« un dump SQL brut ne doit révéler ni identités ni contenus ») n'est alors pas tenu pour
les mineurs :

```
Léa | CM2 - École Jules Ferry | [{"label":"Pointure","value":"34"}]
```

Tranché : **étendre le chiffrement à ces champs**. Le travail restant est décrit dans
`backend/docs/registre-donnees.md` (types Doctrine, migration de chiffrement des lignes existantes,
`schoolLabel` à ajouter au registre, et le tri SQL sur le prénom qui devra passer côté PHP ou par un
index aveugle).

## Hypothèses examinées et écartées

- **« Les e-mails rendus sont empilés en clair dans `messenger_messages` »** — le corps des
  notifications ne contient qu'un titre traduit et un lien ; aucun champ chiffré n'y transite.
- **« La comparaison du payload d'idempotence est absente »** — elle existe : rejouer une clé avec un
  contenu différent renvoie bien `409` (seul le périmètre de la clé est fautif, cf. C10).
- **« L'agenda décale les dates d'un jour »** — non reproduit : le template passe
  `occ.recurrenceDate|date('Y-m-d')`, format que l'API accepte, et `DELETE ?scope=series&from=` est
  cohérent avec `DeleteEventProcessor.php:97`. Le défaut réel de fuseau est C14.
- **« Toast "Nouvelle version disponible" au premier chargement »** — reliquat d'un service worker
  d'une exécution antérieure ; disparu après purge, non reproductible sur une installation neuve.

## Reste à tester

Les tests visuels ont dû s'arrêter (extension Chrome déconnectée). N'ont pas été exercés dans le
navigateur : écran Échanges (proposition, vote, décision, fil de messages), PWA hors ligne,
notifications e-mail de bout en bout, rendu responsive et navigation clavier complète, familles
liées / overlay.
