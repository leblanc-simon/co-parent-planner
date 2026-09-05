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

## 1. Le vote ne dit pas ce qu'il va déclencher — P1

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

## 2. On ne voit pas son propre vote — P1

`OptionView` expose `voteCount` et `decided`, **jamais** « c'est mon choix ». Après avoir voté, rien
ne le rappelle : le décompte passe de 0 à 1, sans dire que ce 1, c'est vous. La maquette, elle,
marque l'option choisie (bordure accentuée, pastille pleine) et remplace le bouton par
« ✓ Choix enregistré ».

**Proposition.** Ajouter `myVote: bool` à `OptionView` (ou `myVotedOptionId` sur l'échange), et
adopter le geste de la maquette : **sélection réversible puis validation explicite**, au lieu d'un
bouton par option. Un clic de sélection ne part pas au serveur ; c'est « Valider mon choix » qui
engage — ce qui règle aussi le point 1 sans ajouter de dialogue.

## 3. Le fil n'est pas une conversation — P2

La maquette aligne **ses propres messages à droite**, avec une bulle teintée, l'auteur et l'heure
sous la bulle. L'implémentation — web comme mobile — pose tous les messages à gauche, avatar puis
texte, dans une liste uniforme : deux parents qui échangent lisent un journal, pas un dialogue. Sur
l'écran dont c'est **l'unique objet**, c'est l'écart le plus visible à la maquette.

**Proposition.** Bulles asymétriques (`m.dir`, `m.bubble` de la maquette), avatar seulement pour
l'autre, heure discrète sous la bulle. Aucun changement de contrat : `authorMembershipId` suffit à
savoir si le message est le sien.

## 4. Une décision que personne ne tranche reste ouverte pour toujours — P2

Le bouton « Clore » n'est offert que pour une **discussion** (`sel.type == 'discussion'` côté web,
`e.type == 'discussion'` côté mobile). Or `ExchangeWriter` accepte parfaitement de clore une décision
**en forçant l'option retenue** (`decidedOptionId`), et refuse seulement de la clore sans gagnant.
Résultat : une décision que l'autre parent n'a jamais votée reste ouverte indéfiniment, affichée
« En retard » pour l'éternité, et **US-EX-6 n'est exposée qu'à moitié**.

**Proposition.** Offrir « Trancher » sur une décision ouverte : choisir l'option retenue, confirmer
(action irréversible, comme la clôture d'une discussion), envoyer `decidedOptionId`. Rien à écrire
côté serveur.

## 5. La carte de liste cache ce qui permet de trier — P2

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

## 6. Rien ne distingue un échange qui a bougé — P2

Aucune notion de lecture n'existe (`grep unread|lastReadAt` → rien). Le badge de navigation compte
les échanges **ouverts**, pas ceux qui ont du nouveau. Sur un écran conversationnel, c'est le manque
le plus sensible à l'usage quotidien : on rouvre chaque fil pour savoir s'il a bougé.

**Proposition.** `ExchangeRead(membership, exchange, last_read_at)` — une ligne par membre et par
échange, écrite à l'ouverture du détail. La liste marque alors les fils ayant des messages postérieurs,
et le badge de navigation peut compter les non-lus plutôt que les ouverts. Donnée technique, non
personnelle (même nature qu'`OverlayPreference`). C'est le point le plus coûteux de cette liste, et
probablement celui qui change le plus le quotidien.

## 7. L'échéance ne se modifie pas depuis l'écran — P3

`UpdateExchangeRequest` accepte `deadline` (et `''` pour l'effacer) ; aucune UI ne l'offre. Une
échéance mal saisie ne se corrige qu'en rouvrant un autre échange — c'est-à-dire jamais.

## 8. Le formulaire de création demande tout, d'emblée — P3

Type, titre, message, enfant, échéance et deux options sont présentés en bloc. La maquette n'a qu'un
bouton « Nouvel échange » et suppose un formulaire plus court. À froid, la friction est réelle pour
l'action la plus fréquente (poser une question simple).

**Proposition.** Réduire à *titre + message*, et ne révéler enfant, échéance et options que si l'on
choisit « Décision » ou si l'on déplie « Plus d'options ».

## 9. Mobile : pas de temps réel sur le seul écran conversationnel — P3 (différé assumé)

`exchange_detail_screen.dart` le documente : « pas de live SSE mobile (différé, cohérent Lots 3/4) ».
C'est défendable pour la garde et l'agenda ; ça l'est moins ici, où deux personnes s'écrivent. Le
backend publie déjà les événements Mercure ; il ne manque que l'abonnement mobile.

---

## Ordre de traitement proposé

| Lot | Contenu | Contrat |
|---|---|---|
| **X1** | Points 1 + 2 : sélection réversible, validation explicite, état du tour, « mon choix » | `OptionView.myVote`, état du tour sur `ExchangeResource` |
| **X2** | Point 4 : trancher une décision (`decidedOptionId` depuis l'UI) | inchangé |
| **X3** | Point 3 : fil en bulles asymétriques (web + mobile) | inchangé |
| **X4** | Point 5 : carte de liste conforme à la maquette | `childName` |
| **X5** | Point 6 : lecture et non-lus | nouvelle ressource |
| **X6** | Points 7, 8, 9 : échéance éditable, formulaire progressif, temps réel mobile | inchangé |

**X1 et X2 se tiennent** : ce sont les deux moitiés d'un même sujet — comprendre, avant de cliquer,
ce que le clic engage. C'est par là qu'il faut commencer.
