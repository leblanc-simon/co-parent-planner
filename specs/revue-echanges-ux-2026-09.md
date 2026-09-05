# Échanges — revue UX/UI de l'implémentation (septembre 2026)

> **Auto-critique de l'écran Échanges (Lot 6), web et mobile**, conduite le 2026-09-05 contre le code
> réel, la maquette `specs/design/CoParentPlanner Desktop.dc.html` (`:417-486`, données `:1325-1350`)
> et `specs/lots/lot-6-echanges-temps-reel.md`. Rien n'est implémenté ici : c'est une proposition,
> ordonnée par valeur, à arbitrer.
>
> **Le constat d'ensemble** : l'écran est *fonctionnellement* complet — on ouvre, on discute, on vote,
> on clôt — mais il est construit comme un **formulaire**, alors que la maquette le construit comme
> une **conversation qui aboutit à une décision**. Les six premiers points ci-dessous découlent tous
> de cet écart-là.

## 1. Le vote ne dit pas ce qu'il va déclencher — P1 ✅ *livré le 2026-09-05 (X1)*

**Ce que fait le code.** Chaque option porte son propre bouton « Valider ». Un clic envoie le vote
immédiatement (`exchanges#decide` / `exchange-validate-{id}`). L'écran affiche, par option, un
décompte (« 1 validation »).

**Ce que la règle fait vraiment.** `DecisionEngine::evaluate()` ne tranche que lorsque **tous** les
électeurs (`exchanges:edit`) ont voté et qu'il existe un gagnant strict. Autrement dit, dans une
famille à deux parents :

- si l'autre parent **n'a pas encore voté**, mon clic est réversible — le vote est upserté ;
- s'il **a déjà voté**, mon clic **tranche définitivement** l'échange, envoie une notification et
  interdit la réouverture (§4.5).

Ces deux situations sont rigoureusement identiques à l'écran. L'utilisateur ne peut pas savoir si son
prochain clic est un brouillon ou une signature.

**Proposition.** Énoncer l'état du tour, comme la maquette l'énonce (`statusLabel`) :

- « En attente de la réponse de Marc » — votre vote reste modifiable ;
- « Marc a voté — votre choix tranchera la décision » — et alors **seulement**, demander confirmation ;
- « Décidé : Centre A », une fois tranché.

Cela demande d'exposer au contrat ce que le serveur sait déjà : qui a voté (sans dire **pour quoi** —
ce serait un vote à bulletin ouvert, à arbitrer) et si le vote de l'appelant existe.

## 2. On ne voit pas son propre vote — P1 ✅ *livré le 2026-09-05 (X1)*

`OptionView` expose `voteCount` et `decided`, **jamais** « c'est mon choix ». Après avoir voté, rien
ne le rappelle : le décompte passe de 0 à 1, sans dire que ce 1, c'est vous. La maquette, elle,
marque l'option choisie (bordure accentuée, pastille pleine) et remplace le bouton par
« ✓ Choix enregistré ».

**Proposition.** Ajouter `myVote: bool` à `OptionView` (ou `myVotedOptionId` sur l'échange), et
adopter le geste de la maquette : **sélection réversible puis validation explicite**, au lieu d'un
bouton par option. Un clic de sélection ne part pas au serveur ; c'est « Valider mon choix » qui
engage — ce qui règle aussi le point 1 sans ajouter de dialogue.

## 3. Le fil n'est pas une conversation — P2 ✅ *livré le 2026-09-05 (X3)*

La maquette aligne **ses propres messages à droite**, avec une bulle teintée, l'auteur et l'heure
sous la bulle. L'implémentation — web comme mobile — pose tous les messages à gauche, avatar puis
texte, dans une liste uniforme : deux parents qui échangent lisent un journal, pas un dialogue. Sur
l'écran dont c'est **l'unique objet**, c'est l'écart le plus visible à la maquette.

**Proposition.** Bulles asymétriques (`m.dir`, `m.bubble` de la maquette), avatar seulement pour
l'autre, heure discrète sous la bulle. Aucun changement de contrat : `authorMembershipId` suffit à
savoir si le message est le sien.

## 4. Une décision que personne ne tranche reste ouverte pour toujours — P2 ✅ *livré le 2026-09-05 (X2)*

Le bouton « Clore » n'est offert que pour une **discussion** (`sel.type == 'discussion'` côté web,
`e.type == 'discussion'` côté mobile). Or `ExchangeWriter` accepte parfaitement de clore une décision
**en forçant l'option retenue** (`decidedOptionId`), et refuse seulement de la clore sans gagnant.
Résultat : une décision que l'autre parent n'a jamais votée reste ouverte indéfiniment, affichée
« En retard » pour l'éternité, et **US-EX-6 n'est exposée qu'à moitié**.

**Proposition.** Offrir « Trancher » sur une décision ouverte : choisir l'option retenue, confirmer
(action irréversible, comme la clôture d'une discussion), envoyer `decidedOptionId`. Rien à écrire
côté serveur.

## 5. La carte de liste cache ce qui permet de trier — P2 ✅ *livré le 2026-09-05 (X4)*

Elle affiche : type, retard, décidé, titre, « Un enfant » ou « Toute la famille », le nombre de
messages, l'échéance. Elle **n'affiche pas** :

- **le prénom de l'enfant concerné** — le gabarit écrit littéralement « Un enfant » alors que la liste
  des enfants est déjà passée à la vue pour le formulaire de création, et que la maquette montre le
  prénom, coloré (`q.childTag`) ;
- **l'auteur** — la maquette le montre avec son avatar (`q.author`), c'est ce qui permet de repérer
  « ce que l'autre m'a demandé » ;
- **la fraîcheur** — ni date du dernier message, ni indicateur de nouveauté (voir le point 6).

**Proposition.** Reprendre la carte de la maquette : titre, badge d'état, puis une ligne
« avatar + auteur · N réponses · prénom de l'enfant ». Le prénom demande soit `childName` au contrat,
soit une résolution côté vue à partir de la liste déjà disponible.

## 6. Rien ne distingue un échange qui a bougé — P2 ✅ *livré le 2026-09-05 (X5)*

Aucune notion de lecture n'existe (`grep unread|lastReadAt` → rien). Le badge de navigation compte
les échanges **ouverts**, pas ceux qui ont du nouveau. Sur un écran conversationnel, c'est le manque
le plus sensible à l'usage quotidien : on rouvre chaque fil pour savoir s'il a bougé.

**Proposition.** `ExchangeRead(membership, exchange, last_read_at)` — une ligne par membre et par
échange, écrite à l'ouverture du détail. La liste marque alors les fils ayant des messages postérieurs,
et le badge de navigation peut compter les non-lus plutôt que les ouverts. Donnée technique, non
personnelle (même nature qu'`OverlayPreference`). C'est le point le plus coûteux de cette liste, et
probablement celui qui change le plus le quotidien.

## 7. L'échéance ne se modifie pas depuis l'écran — P3 ✅ *livré le 2026-09-05 (X6)*

`UpdateExchangeRequest` accepte `deadline` (et `''` pour l'effacer) ; aucune UI ne l'offre. Une
échéance mal saisie ne se corrige qu'en rouvrant un autre échange — c'est-à-dire jamais.

## 8. Le formulaire de création demande tout, d'emblée — P3 ✅ *livré le 2026-09-05 (X6)*

Type, titre, message, enfant, échéance et deux options sont présentés en bloc. La maquette n'a qu'un
bouton « Nouvel échange » et suppose un formulaire plus court. À froid, la friction est réelle pour
l'action la plus fréquente (poser une question simple).

**Proposition.** Réduire à *titre + message*, et ne révéler enfant, échéance et options que si l'on
choisit « Décision » ou si l'on déplie « Plus d'options ».

## 9. Mobile : pas de temps réel sur le seul écran conversationnel — P3 ✅ *livré le 2026-09-05 (X6)*

`exchange_detail_screen.dart` le documente : « pas de live SSE mobile (différé, cohérent Lots 3/4) ».
C'est défendable pour la garde et l'agenda ; ça l'est moins ici, où deux personnes s'écrivent. Le
backend publie déjà les événements Mercure ; il ne manque que l'abonnement mobile.

---

## Ordre de traitement proposé

| Lot | Contenu | Contrat |
|---|---|---|
| **X1** ✅ | Points 1 + 2 : sélection réversible, validation explicite, état du tour, « mon choix » | `OptionView.myVote`, état du tour sur `ExchangeResource` |
| **X2** ✅ | Point 4 : trancher une décision (`decidedOptionId` depuis l'UI) | inchangé |
| **X3** ✅ | Point 3 : fil en bulles asymétriques (web + mobile) | inchangé |
| **X4** ✅ | Point 5 : carte de liste conforme à la maquette | `childName` |
| **X5** ✅ | Point 6 : lecture et non-lus | nouvelle ressource |
| **X6** ✅ | Points 7, 8, 9 : échéance éditable, formulaire progressif, temps réel mobile | inchangé |

**X1 et X2 se tiennent** : ce sont les deux moitiés d'un même sujet — comprendre, avant de cliquer,
ce que le clic engage. C'est par là qu'il faut commencer.

---

## Journal

### 2026-09-05 — X1 et X2 livrés

**Le vote se fait désormais en deux gestes.** Choisir une option est **local et réversible** — rien
ne part au serveur ; c'est « Valider mon choix » qui engage. Le contrat porte l'état du tour, calculé
là où la règle vit déjà (`DecisionEngine`) plutôt que redérivé côté web et côté mobile :
`myVotedOptionId`, `awaitingVoters` (qui manque à l'appel, **hors moi**) et `myVoteMaySettle`.

Trois décisions de conception qui méritent d'être dites :

- **« peut » trancher, pas « va » trancher.** Même quand tous les autres ont voté, une **égalité**
  laisse l'échange ouvert (§3.3) : annoncer un tranchage certain serait faux une fois sur deux.
- **Confirmation seulement quand elle a un sens.** Elle n'apparaît que si le vote peut trancher. La
  réclamer à chaque fois apprendrait à passer outre, et la plupart des votes restent modifiables.
- **Agrégats, jamais de scrutin ouvert.** On dit qui n'a pas encore voté ; on ne dit jamais pour quoi
  chacun a voté. Un décompte entre coparents n'est pas un vote à bulletin ouvert — c'était une
  question laissée ouverte par la revue, elle est tranchée dans ce sens.

**X2 — trancher une décision que personne ne vote.** Le serveur l'acceptait depuis toujours
(`decidedOptionId`) ; aucun écran ne l'offrait, si bien qu'une décision non votée restait ouverte,
et « en retard », indéfiniment. Action disponible sur les deux plateformes, sous confirmation nommée.

**Flakiness E2E : la cause racine trouvée, et elle était dans le produit.** Deux tests échouaient une
fois sur deux ou trois. La confirmation de succès n'était **jamais masquée** : elle restait à
l'écran, si bien qu'attendre « la confirmation » pouvait être satisfait par le message de la mutation
**précédente**, et l'assertion suivante s'exécutait alors pendant le rechargement. Le socle
`ApiController` masque désormais la confirmation dès qu'une nouvelle mutation part — ce qui est aussi
la bonne UX : un message de succès affiché à côté d'un geste en cours laisse croire qu'il a abouti.
Trois exécutions consécutives vertes après correction.

**Portes** : backend **438 tests / 3 778 assertions** (435 avant), PHPStan 0, cs-fixer 0, Twig 38 ;
mobile **399 tests** (393 avant), couverture **82,8 %**, `dart analyze --fatal-infos` 0, format 0 ;
**E2E 23/23** ×3. OpenAPI ré-exporté, client Dart régénéré sans drift.

**Restent** X3 (fil en bulles asymétriques), X4 (carte de liste), X5 (non-lus) et X6.

### 2026-09-05 — X3, X4 et X5 livrés

**X3 — le fil est redevenu un dialogue.** Tous les messages étaient posés à gauche, avatar compris :
deux parents qui s'écrivent lisaient un journal, pas une conversation. Les siens vont à droite, sans
avatar (on sait qui l'on est) et sans son propre nom sous la bulle ; ceux de l'autre restent à
gauche, nommés. Web (`.exchange-message--mine`) et mobile (`_Bubble(mine:)`).

**X4 — la carte de liste nomme enfin les gens.** Elle écrivait « Un enfant » alors qu'elle avait
l'identifiant sans le prénom, et taisait l'auteur. `ExchangeResource.childName` entre au contrat :
le mobile le résolvait depuis la liste des enfants, qui n'est pas toujours chargée — un échange
concernant Léa s'annonçait alors « Toute la famille ». Le test web a levé un oubli au passage : le
**détail** disait lui aussi « Un enfant concerné ».

**X5 — les non-lus.** Rien ne distinguait un fil qui venait de recevoir trois messages d'un fil
dormant depuis un mois : il fallait ouvrir chacun pour le savoir. Nouvelle entité `ExchangeRead`
(membre × échange), `POST /api/exchanges/{id}/read`, `hasUnread` sur la ressource.

Quatre décisions de conception :

- **Le marquage est un POST, jamais un effet de bord du GET.** Le projet avait déjà tranché ainsi
  pour l'aperçu d'invitation (« GET = aperçu non consommant »). Ce n'est pas une coquetterie REST :
  Turbo précharge les liens au survol, et un GET qui écrit aurait fait tomber les pastilles de fils
  jamais ouverts. Côté web c'est le navigateur qui poste, silencieusement (ni bandeau, ni
  rechargement) ; côté mobile, une fois par ouverture d'écran.
- **Le repère est un identifiant de message, pas une date.** La première version comparait
  `dernière activité > dernière lecture` — et **le test l'a prise en défaut** : les horodatages du
  schéma sont stockés à la seconde, si bien qu'une réponse postée dans la même seconde qu'une lecture
  passait pour déjà lue. `ExchangeRead.lastReadMessage` compare des `Uuid::v7` (ordonnés dans le
  temps) : exact, sans dépendre de la précision d'horloge. Une requête `DISTINCT ON` pour toute la
  liste — PostgreSQL 16 n'a pas d'agrégat `max(uuid)`.
- **Écrire vaut lire.** Créer un échange ou y poster marque son auteur lecteur : sans cela il
  retrouverait ses propres écrits en gras, et la règle cesserait d'être une simple comparaison.
- **`exchanges:view` suffit.** Marquer lu n'est pas écrire dans l'échange ; un membre en lecture
  seule doit pouvoir faire tomber son propre marqueur. Rien n'est publié sur Mercure : la lecture est
  strictement personnelle — l'API ne renvoie que `hasUnread`, du seul point de vue de l'appelant,
  et personne ne peut savoir quand l'autre parent a ouvert un fil. Entrée de registre ajoutée
  (`ExchangeRead.lastReadAt`, catégorie technique).

Le marqueur ne tient jamais à la seule couleur (§8.1) : pastille **et** graisse du titre **et**
« Non lu » pour les lecteurs d'écran, des deux côtés.

**Portes** : backend **443 tests / 3 906 assertions** (438 avant), PHPStan 0, cs-fixer 0, Twig 38 ;
mobile **404 tests** (401 avant), couverture **82,9 %**, `dart analyze` 0, format 0 ; **E2E 23/23**
×2 (une première exécution avait échoué sur deux `waitForSaved` — cache Symfony froid juste après un
`cache:clear`, 1 min 20 contre 47 s ensuite). OpenAPI ré-exporté, client Dart régénéré sans drift.

**Reste** X6 : échéance éditable, formulaire de création progressif, temps réel mobile.

### 2026-09-05 — X6 livré : la revue est CLOSE (9 points sur 9)

**Point 7 — l'échéance se corrige depuis l'écran.** `UpdateExchangeRequest` l'acceptait depuis
toujours (`''` efface, `null` = inchangée) ; aucune UI ne l'offrait, si bien qu'une échéance mal
saisie ne se corrigeait qu'en rouvrant un autre échange, c'est-à-dire jamais. Ligne d'action
discrète sous le corps de l'échange, des deux côtés, avec un bouton « Retirer » distinct. La nuance
`''` / `null` — tout le contrat de cet endpoint — n'était couverte par aucun test : elle l'est
maintenant, y compris le cas « un PATCH qui ne parle pas d'échéance ne l'efface pas ».

**Point 8 — le formulaire ne demande plus tout d'emblée.** Type, titre, message ; l'enfant et
l'échéance se déplient derrière « Plus d'options », et se déplient **d'eux-mêmes** quand on choisit
« Décision » — une décision a presque toujours une date. On déplie, jamais on ne replie : refermer
sous les doigts de qui vient d'y saisir quelque chose serait pire que de laisser ouvert.

**Point 9 — le temps réel mobile, qui était le vrai reste à faire.** Les Lots 3, 4 et 6 l'avaient
différé ; défendable pour la garde et l'agenda, beaucoup moins sur l'écran où deux personnes
s'écrivent. Le backend publiait déjà ; il ne manquait que l'abonnement.

- **Ce que le serveur pousse est un signal, pas une charge utile** (`<turbo-stream action="refresh">`,
  destiné au web). Le mobile n'a donc rien à désérialiser : il relit l'état canonique, exactement
  comme après une mutation. C'est ce qui rend l'abonnement sans risque — aucun contenu ne transite
  par un chemin qui ne serait pas déjà testé.
- **Les topics lisibles restent décidés par le serveur** : le jeton vient de
  `GET /api/realtime/token`, scopé à la famille active (§3.12). Le client ne choisit pas ce qu'il
  voit.
- **L'abonnement vit dans un provider `autoDispose`**, pas dans un `State` d'écran : il se ferme tout
  seul quand plus personne n'écoute, et se rouvre à la bascule de famille — un abonnement qui lui
  survivrait écouterait les topics de l'ancienne.
- **Une coupure est la situation normale d'un flux long**, pas une erreur à remonter : reprise avec
  attente croissante plafonnée. Le premier signal reçu après reconnexion réaligne l'écran, puisqu'il
  déclenche une relecture complète.
- Le **découpage SSE** est isolé (`core/realtime/sse.dart`) parce que c'est la seule partie pure, et
  celle où une erreur se voit le moins : compter les battements de cœur de Mercure ferait recharger
  l'écran toutes les quinze secondes. Six tests l'encadrent.

**Portes** : backend **445 tests / 3 947 assertions** (443 avant), PHPStan 0, cs-fixer 0, Twig 38 ;
mobile **418 tests** (404 avant), couverture **83,0 %**, `dart analyze` 0, format 0 ; **E2E 23/23**
(le parcours Lot 6 exerce désormais la correction d'échéance de bout en bout). Aucune dépendance
ajoutée — l'abonnement SSE tient sur `dio`, déjà présent : `INSTALL.md` est inchangé.
