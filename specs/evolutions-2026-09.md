# Évolutions demandées — septembre 2026

> **Document de suivi.** Deux demandes produit, analysées le 2026-09-05 contre le code réel
> (backend Symfony, front web Twig/Stimulus, app Flutter) et contre les sources de vérité :
> `specs/cdc.md` (§8 = design system, §8.1 = amendement accessibilité), `CLAUDE.md` (invariants),
> `specs/lots/*.md`. Rien n'est implémenté à ce stade.
>
> **A.** Associer un parent et un beau-parent.
> **B.** Choisir sa couleur dans le planning de garde, sans affecter la vue de l'autre parent.

## Arbitrages rendus (2026-09-05)

Quatre points ont été tranchés avant analyse ; ils ferment les ambiguïtés qui changeaient la
conception :

| # | Question | Réponse retenue |
|---|---|---|
| B-1 | Portée du choix de couleur | **Préférence locale au spectateur** : chacun choisit, pour son propre affichage, la couleur de chaque parent. Mon choix ne modifie rien chez l'autre. |
| B-2 | Surfaces repeintes | **Le planning de garde seulement** (cases A/B, mini-semaine de l'accueil, fiche de jour, légendes). |
| A-1 | Finalité de l'association | **Les quatre** : affichage (« conjoint·e de X »), foyer (rattachement aux jours de garde du parent), droits (proposer ceux du parent), notifications (traiter le couple ensemble). |
| A-2 | Déclaration et cardinalité | **Un admin déclare**, **1 parent ↔ 1 beau-parent**. |

Quatre points de plus, tranchés le même jour :

| # | Question | Réponse retenue |
|---|---|---|
| A-3 | Diffusion des notifications au conjoint | **Sous droit explicite** : le conjoint ne reçoit que ce que ses droits l'autorisent déjà à recevoir. *Voir l'encadré du lot E3 : le code satisfait déjà cette règle.* |
| A-4 | Portée du lien « conjoint » | **Strictement interne à une famille.** Le foyer recomposé vu d'en face reste une **famille liée** (Lot 5), en lecture seule. |
| B-3 | Emplacement du choix des couleurs | **Paramètres › Planning de garde.** |
| B-4 | Liberté de couleur | **Palette imposée de 8 teintes** validées pour le contraste, dont les deux du §8. |

---

## A — Associer un parent et un beau-parent

### État actuel, vérifié dans le code

`Membership` (cdc §5.2) porte `user_id`, `family_id`, `role` (`parent`|`step_parent`|`child`),
`is_admin`, `child_id`, `display_color`, `status`. **Aucune relation entre memberships** : rien
n'exprime qu'un beau-parent accompagne tel parent plutôt que tel autre. Un foyer recomposé peut
aujourd'hui se modéliser de deux façons — un beau-parent **membre de la même famille** (rôle
`step_parent`), ou une **famille liée** (Lot 5, overlay lecture seule) — et la demande porte sur le
premier cas.

### Ce qu'il faut ajouter

**Modèle.** Une auto-référence nullable sur `Membership` :

```
partner_membership_id  UUID NULL  → memberships(id)  ON DELETE SET NULL
```

Portée par le **beau-parent** (`step_parent.partner = parent`), source de vérité unique. La vue
inverse (« Sophie · conjointe : Bob ») se déduit par requête. L'alternative — écrire la relation des
deux côtés — impose de tenir deux colonnes cohérentes à chaque mutation, et la première divergence
serait invisible : écartée.

**Règles de validation** (processor + test fonctionnel par règle) :

- les deux memberships appartiennent à la **même famille** (jamais un scope croisé, `CLAUDE.md`) ;
- rôles : la cible est `parent`, le porteur est `step_parent` — pas d'auto-association, pas de
  parent↔parent (ce sont les deux coparents, pas un couple) ;
- **1 ↔ 1** : contrainte d'unicité sur `partner_membership_id` (un parent n'a qu'un conjoint dans la
  famille) et unicité implicite du porteur (une ligne par membership) ;
- droit requis : `family_settings:edit` **et** `is_admin`, comme les autres mutations de structure ;
- retrait d'un membre → `SET NULL` (l'association disparaît, le reste survit) ;
- anonymisation RGPD (US-SET-4b) : la colonne suit le membership, rien à anonymiser en propre.

**Registre des données personnelles — obligatoire.** Ce champ relie deux personnes identifiées et
révèle une information de **vie privée** (« Bob est le conjoint de Sophie »). Il n'est pas chiffrable
(c'est une clé étrangère : le chiffrer casserait les jointures), mais il doit être **déclaré**
(`#[PersonalData]` + entrée dans `backend/docs/registre-donnees.md`), sans quoi le test CI de
cohérence échoue — et il doit l'être à raison, pas seulement pour faire passer la porte.

### Les quatre finalités, par ordre de coût

**1. Affichage (coût faible, aucun risque).** Liste des membres, liste d'accès d'un enfant, feuille
de bascule : « Bob Martin · Beau-parent, conjoint de Sophie ». Le nom du parent est un champ 🔒
déchiffré — donc soumis au même droit de lecture que partout ailleurs.

**2. Foyer : rattachement aux jours de garde (coût moyen, invariant à protéger).** Le planning peut
indiquer que le beau-parent est présent les jours de « son » parent : fiche de jour « chez Sophie
(avec Bob) », légende du calendrier, choix du responsable d'un événement.

> **Invariant à ne pas franchir** : le moteur de garde reste inchangé. `CustodyDay.parent_membership`
> continue de désigner **le parent**, la priorité exception > vacances > base est intacte, et les
> statistiques restent calculées sur les jours effectifs. Le rattachement est de la **présentation**.
> Toute tentation de « compter » les jours du beau-parent romprait `CLAUDE.md`.

**3. Droits proposés (coût faible, sans effet sur la sécurité).** À l'invitation d'un beau-parent et
à l'édition de ses droits, préremplir avec ceux du parent associé. `RightsNormalizer::capForRole`
continue de plafonner côté serveur — la proposition est une commodité de saisie, jamais une
autorisation. Le récapitulatif des droits (US-INV-1, CA §3.8) doit montrer ce qui sera **réellement**
appliqué, comme le constat mobile C50 l'a établi.

**4. Notifications : traiter le couple ensemble (arbitrage A-3 — déjà satisfait par le code).**

> **Vérifié après l'arbitrage, et il change le contenu du lot.** La règle demandée — « le conjoint
> reçoit, mais seulement sous son droit » — est **exactement celle qui s'applique aujourd'hui** :
> `RecipientResolver::resolve()` retient *tous* les memberships actifs de la famille ayant `view` sur
> le domaine de la notification, sans distinction de rôle. Un beau-parent qui a le droit reçoit déjà ;
> un beau-parent qui ne l'a pas ne doit pas recevoir. **L'association n'ajoute donc rien à la
> diffusion**, et il n'y a pas de code de diffusion à écrire.
>
> Le lot E3 se réduit à ce qui reste utile : **nommer le foyer** dans le corps de l'e-mail
> (« chez Sophie et Bob » plutôt que « chez Sophie »). Si l'intention était d'élargir la diffusion
> *au-delà* des droits, il faut le dire explicitement — ce serait une exception au modèle
> d'autorisation, à écrire dans le cdc §2.3 avant d'être implémentée.

### Surfaces à livrer (web **et** mobile, DoD de lot)

| Surface | Web | Mobile |
|---|---|---|
| Édition d'un membre (sélecteur « conjoint·e ») | `settings.html.twig` onglet Famille + `settings_controller.js` | `members_screen.dart` |
| Mention « conjoint·e de X » | liste des membres, liste d'accès enfant | idem |
| Fiche de jour (« avec Bob ») | `_garde_day.html.twig` | `CustodyDaySheet` |
| Préremplissage des droits | `invite_screen` web | `invite_screen.dart` |
| Contrat | `MemberResource` (+ `partnerMembershipId`, `partnerName`), `UpdateMembershipRequest` | client régénéré depuis l'OpenAPI |

---

## B — Couleur du planning choisie par chacun

### État actuel, vérifié dans le code

Deux mécanismes de couleur coexistent, et la demande porte sur le second :

1. **`Membership.display_color`** — couleur d'identité, **une par membre, vue par tout le monde**.
   Elle peint les avatars, les auteurs de notes et de messages, la liste d'accès d'un enfant.
   Changer la sienne change bien l'écran des autres.
2. **Les deux teintes du planning de garde** — `--accent` (#C2643D, parent A) et `--positive`
   (#2E7D71, parent B), **tokens normatifs du cdc §8**, écrits en dur dans les feuilles de style et
   dans `AppColors` : elles ne dépendent d'aucune donnée, seulement du slot A/B du rythme.

Points de contact recensés : web `.garde-day--a/--b`, `.garde-dot--a/--b`,
`.home-custody__day--a/--b`, `.day-sheet__parent--a/--b`, `.home-hero--a/--b` ; mobile
`custody_screen.dart`, `home_tab.dart`, `custody_settings_screen.dart` (≈ 14 usages directs de
`AppColors.accent`/`positive`).

### Conception retenue

**Une préférence par spectateur et par membre observé.** Sur le modèle exact d'`OverlayPreference`
(Lot 5 §5.2), qui est le précédent maison d'une préférence d'affichage :

```
custody_color_preferences
  membership_id         → memberships(id)  (le SPECTATEUR)
  target_membership_id  → memberships(id)  (le parent observé)
  color                 varchar(16)
  UNIQUE (membership_id, target_membership_id)
```

**Clé sur le membership observé, pas sur le slot A/B** : un changement de rythme peut échanger les
titulaires de A et de B ; une préférence attachée au slot suivrait la place, pas la personne, et la
couleur « de Sophie » sauterait sur Marc. C'est aussi ce qui permettra d'étendre la surface plus tard
sans re-modéliser.

**Donnée technique, non personnelle** — comme `OverlayPreference` : aucune entrée de registre, mais
la mention explicite dans le docblock de l'entité, pour que le prochain lecteur n'ait pas à trancher.

**Rendu.** Le serveur résout les deux couleurs du spectateur et les expose au gabarit ; le CSS lit
des variables au lieu de constantes :

```css
.garde-day--a { background: var(--slot-a-soft); border-color: var(--slot-a); }
```

Les tokens du §8 restent les **valeurs par défaut** : sans préférence, l'écran est exactement celui
d'aujourd'hui. Côté mobile, un `custodyPaletteProvider` remplace les constantes aux ≈14 points de
contact.

### Contraintes non négociables

- **§8.1 (accessibilité)** — le calendrier ne doit **jamais** informer par la seule couleur. Les
  marqueurs non chromatiques (initiales des parents) restent affichés quoi qu'il arrive : c'est ce
  qui rend une palette libre acceptable. Toute idée de « supprimer les initiales puisque les couleurs
  sont personnalisées » romprait l'amendement.
- **Palette imposée de 8 teintes (arbitrage B-4).** Huit couples *fond doux + bordure + encre*
  validés pour le contraste, dont les deux du §8 (terracotta et teal) en valeurs par défaut. Pas de
  sélecteur libre : le contraste du texte de l'initiale sur le fond de case doit rester lisible, et
  c'est cette initiale qui porte l'information depuis l'amendement §8.1. Les deux slots doivent
  rester distincts — la même teinte des deux côtés est refusée.
- **Dérogation assumée au §8.** Le cdc fixe accent = parent A et positive = parent B. Les rendre
  configurables *localement* est une dérogation : elle doit être **écrite dans le §8** (« palette par
  défaut, surchargeable par spectateur ») avant l'implémentation, sinon la revue graphique suivante
  la signalera comme un écart.
- **Hors-ligne** — la préférence doit être lisible sans réseau (cache de lecture mobile, cookie ou
  cache web), sinon le planning changerait de couleur en mode avion.

### Surfaces à livrer

| Surface | Web | Mobile |
|---|---|---|
| Choix des couleurs (arbitrage B-3) | Paramètres › **Planning de garde** (onglet `tab=garde`) | écran de réglages de la garde |
| Rendu | variables CSS posées par le gabarit | `custodyPaletteProvider` |
| Contrat | nouvelle ressource `CustodyColorPreference` (GET collection + PUT) | client régénéré |

> **Conséquence de l'emplacement (B-3), à ne pas manquer** : l'onglet « Planning de garde » n'est
> rendu que pour une famille séparée, et ses sections actuelles (rythme, exceptions, vacances) sont
> réservées à `custody:edit`. Or **choisir ses couleurs est une préférence personnelle** : elle doit
> être offerte à quiconque **voit** le planning — un beau-parent en `custody: view` compris. La
> section des couleurs relève donc de `custody ≥ view`, pas de `custody:edit`, contrairement à ses
> voisines. Idem côté mobile.

---

## Découpage proposé

| Lot | Contenu | Dépendances |
|---|---|---|
| **E1** ✅ | A — modèle, contrat, règles de validation, registre RGPD, affichage « conjoint·e de X » (web + mobile) | aucune |
| **E2** ✅ | A — foyer : fiche de jour, responsable d'événement, préremplissage des droits | E1 |
| **E3** ✅ | A — notifications : **nommage** du foyer dans les e-mails. La diffusion, elle, n'a rien à changer : la règle demandée est déjà celle du code (voir A-4 ci-dessus) | E1 |
| **E4** ✅ | B — amendement du §8, palette encadrée, modèle + contrat de la préférence | aucune (parallélisable avec E1) |
| **E5** ✅ | B — rendu web (variables CSS) et mobile (provider), choix de couleurs, hors-ligne | E4 |

**DoD par lot** (identique aux lots produits) : tests unitaires + fonctionnels API + E2E web
Playwright + tests Flutter, migration fournie, OpenAPI ré-exporté et client Dart régénéré sans drift,
i18n câblée, registre des données à jour, `INSTALL.md` si une dépendance bouge, analyse statique
propre, écrans conformes au §8.

## Ce qui reste à décider

Les huit questions de conception sont tranchées ; il ne reste que des points qui se règlent **au
moment d'implémenter**, et qui n'engagent pas le modèle :

1. **Les 8 teintes de la palette** (B-4) : à choisir avec les contrastes mesurés, en gardant
   terracotta et teal en tête de liste. Ce choix appartient au lot E4 et sera soumis avant codage.
2. **Le libellé exact du foyer dans les e-mails** (E3) : « chez Sophie et Bob », « chez Sophie
   (avec Bob) » — à arbitrer sur un rendu réel dans Mailpit.
3. **Un seul point pourrait rouvrir une décision** : si l'intention de A-3 était d'élargir la
   diffusion *au-delà* des droits (un conjoint sans droit `agenda` recevant les notifications
   d'agenda), il faut le dire — ce serait une exception au modèle d'autorisation du cdc §2.3, pas
   une option d'implémentation.

---

## Journal

### 2026-09-05 — E1 et E4 livrés

**E1 — association parent ↔ beau-parent.** `Membership.partner_membership_id`, auto-référence
nullable **portée par le beau-parent**, `SET NULL` au retrait du parent visé, index unique (1 ↔ 1).
`PartnerWriter` centralise les quatre refus — cible hors famille, rôles incompatibles,
auto-association, parent déjà pris — et **accepte les deux sens de saisie** : l'admin désigne le
conjoint depuis la ligne du parent ou depuis celle du beau-parent, sans avoir à savoir de quel côté
la relation est stockée. Un changement de rôle qui rend le couple absurde (promotion en parent) le
retire de lui-même plutôt que de le laisser survivre en incohérence. Rendu **des deux côtés** par un
index miroir construit en une passe sur la collection déjà chargée, côté API comme côté gabarit.
Entrée au **registre des données personnelles** : le champ relie deux personnes identifiées et
énonce une information de vie privée ; il n'est pas chiffrable (clé étrangère) et sa protection tient
au contrôle d'accès. **13 tests API + 1 test web + 2 tests mobiles.**

**E4 — couleurs du planning par spectateur.** §8 **amendé** (nouveau §8.2) avant toute
implémentation, comme prévu : les tokens du §8 restent les valeurs par défaut, la personnalisation
est locale au spectateur, la palette est fermée à 8 teintes et le §8.1 s'applique sans exception —
les initiales des parents restent affichées quelle que soit la palette. `CustodyColorPreference`
est clé sur **(spectateur, membre observé)** et non sur le slot A/B. Deux règles portées par le
serveur : une teinte hors catalogue est refusée, et les deux parents ne peuvent pas partager la même
couleur. Lecture et écriture sous **`custody ≥ view`** — choisir ses couleurs est une préférence
personnelle, offerte à qui **voit** le planning, un beau-parent en lecture compris.

**Deux pièges d'outillage rencontrés, à retenir** :

> **Le cache de métadonnées d'API Platform survit à l'ajout d'une propriété.** Un champ ajouté à une
> ressource n'apparaissait ni dans les réponses ni dans l'OpenAPI exporté, alors qu'il était bien
> écrit en base — la sonde SQL le montrait. `php bin/console cache:clear` (dev **et** test) est
> nécessaire après tout ajout de propriété, sinon on cherche le défaut dans le mauvais fichier.
>
> **Les entités chargées avant une requête HTTP sont détachées** par la réinitialisation des services
> du client de test : les modifier ne produit aucun `UPDATE`, en silence. Il faut les relire après la
> requête.

**Deux garde-fous ont mordu, et c'est ce qu'on leur demande** : la porte de drift du client Dart
(corrigée au constat C67) a vu les fichiers générés **non suivis** du nouveau client ; et le test de
contrat des routes mobiles (C17) a exigé que `/families/{familyId}/custody-colors` soit
explicitement déclarée comme scopée famille, plutôt que de la laisser passer sans en décider.

**Course E2E corrigée au passage** : `lot6-echanges` attendait une confirmation de succès après un
**clic de navigation** — il n'attendait en fait que le message resté à l'écran depuis la mutation
précédente, et ne passait que si l'assertion devançait la navigation. Le test attend désormais le
volet de détail lui-même. Même famille que la course fermée par `waitForReload` à la vague 2.

**Portes** : backend **435 tests / 3 727 assertions** (421 avant), PHPStan 0, cs-fixer 0, Twig 38 ;
mobile **393 tests**, couverture 82,5 %, `dart analyze --fatal-infos` 0, format 0, client Dart
régénéré ; **E2E 23/23**. OpenAPI ré-exporté, deux migrations fournies, registre à jour.

**Reste** : E2 (foyer), E3 (nommage du foyer dans les e-mails) et E5 (rendu des couleurs, web et
mobile, avec le choix dans Paramètres › Planning de garde sous `custody ≥ view`).

### 2026-09-08 — E2, E3 et E5 livrés : les cinq lots sont clos

**E2 — le foyer.** Un service unique, `Household`, nomme le foyer d'un parent (« Sophie », ou
« Sophie et Bob ») et répond à « qui accompagne ce parent ? » **quel que soit le côté qui porte la
relation** — la colonne vit sur le beau-parent, la question se pose le plus souvent depuis le
parent. Trois surfaces l'emploient : la **fiche de jour** (« Chez Sophie » / « avec Bob », web et
mobile), le **sélecteur de responsable** d'une activité (« Bob · conjoint·e de Sophie », qui situe
enfin les quatre adultes d'un foyer recomposé), et le **préremplissage des droits**.

> **L'invariant du moteur de garde n'a pas bougé d'un octet.** `CustodyDay` continue de désigner le
> seul parent, la priorité exception > vacances > base est intacte, et les statistiques restent
> calculées sur les jours effectifs. Le rattachement du beau-parent est de la **présentation** :
> rien n'est compté au conjoint.

Le préremplissage est resté **strictement une commodité de saisie** : à l'invitation, un sélecteur
« Reprendre les droits de » recopie ceux d'un parent dans le formulaire ; sur la ligne d'un
beau-parent associé, un bouton fait de même. Rien n'est accordé tant que le formulaire n'est pas
soumis, et `RightsNormalizer::capForRole` plafonne ensuite côté serveur. Côté mobile, la reprise
**rabaisse elle-même** `edit` à `view` pour un rôle enfant : sans cela, la commodité aurait contourné
le constat C50 (« l'écran doit montrer ce qui sera réellement appliqué »).

> **Limite assumée, à décider si elle gêne** : l'association elle-même n'est **pas** posée à
> l'invitation. Elle relie deux *memberships*, or celui de la personne invitée n'existe qu'à
> l'acceptation ; la déclarer d'avance demanderait de porter le champ sur `Invitation`, de le
> revalider à l'acceptation (le parent visé peut avoir été retiré ou déjà pris entre-temps) et
> d'étendre le contrat. Le document ne le demandait pas ; l'admin déclare donc le couple depuis la
> liste des membres, une fois l'invitation acceptée.

**E3 — nommer le foyer dans l'e-mail.** L'e-mail de garde disait « le planning de votre famille a
été modifié », ce qui oblige à ouvrir l'application pour savoir quoi. Quand la modification porte
sur **un jour identifié** (un échange de journée), le corps dit désormais « Le 12 septembre 2026 est
désormais chez Sophie et Bob ». Sur une période de plusieurs jours ou un changement de rythme, la
phrase serait fausse : le corps générique est conservé.

> **Le payload ne transporte que des identifiants.** Le nom est résolu **à l'envoi**, pas à
> l'émission : un payload Messenger vit en clair dans `messenger_messages`, et y recopier un
> `full_name` 🔒 le ferait fuiter dans un dump SQL brut — ce que le §7 interdit précisément. La date
> est formatée par ICU dans la langue du destinataire et le fuseau de la famille, jamais par un
> format PHP écrit à la main.

Conformément à l'arbitrage A-3, **aucune ligne de diffusion n'a été écrite** : `RecipientResolver`
retenait déjà tous les membres actifs ayant `view` sur le domaine, sans distinction de rôle.

**E5 — le rendu des couleurs.** Le web lit désormais `--slot-a*` / `--slot-b*` au lieu des tokens
`--accent` / `--positive`, posés **côté serveur** sur le conteneur applicatif par
`CustodyPaletteExtension`. Conséquence heureuse : la page mise en cache par le service worker embarque
déjà ses couleurs, et le planning ne change pas d'apparence en mode avion — l'exigence hors-ligne est
satisfaite sans une ligne de JavaScript. Le mobile a son `custodyPaletteProvider` et son cache de
lecture. Le choix vit dans **Paramètres › Planning de garde** sous `custody ≥ view` (arbitrage B-3),
des deux côtés : côté mobile, l'écran de réglages porte donc deux sections sous **deux droits
différents**, et le formulaire de rythme s'y masque de lui-même — le droit est vérifié là où il
s'applique, plutôt qu'à l'entrée du chemin.

**Trois défauts trouvés par les garde-fous, et c'est ce qu'on leur demande :**

> **La règle « deux slots distincts » ne tenait pas sur un planning vierge.** Elle ne comparait
> qu'aux préférences **enregistrées** : sur une famille neuve, peindre le parent B en terracotta
> alors que le parent A l'est **par défaut** passait sans broncher — deux slots identiques, exactement
> ce que le §8.2 interdit. C'est l'E2E, au premier choix de couleur, qui l'a montré. La comparaison
> porte désormais sur les teintes **effectives**, défauts du §8 compris, et un test API la verrouille.
>
> **Le sélecteur de responsable débordait de 74 px** dès que le libellé portait le foyer : un
> `DropdownButtonFormField` se dimensionne sur son contenu, l'ellipse ne joue pas sans `isExpanded`.
> Trouvé par le test widget, pas à l'œil.
>
> **Deux tests de l'écran de réglages mobile ne déclaraient aucun droit** : ils passaient parce que
> l'écran n'était atteignable que par un chemin réservé à `custody:edit`. En vérifiant le droit dans
> l'écran lui-même, ils se sont mis à échouer — le test était plus faible que ce qu'il prétendait.

**Écart de rendu assumé, écrit au cdc §8.2.** La table des huit teintes devient la **source unique**
(CSS, enum PHP, catalogue Dart). Elle donne au fond du parent A `#F6E7DE` là où le token historique
valait `#FBF4EE`, presque blanc — le parent B, lui, était franchement teinté : les deux n'avaient pas
le même poids visuel. Le fond de A est donc légèrement plus soutenu qu'avant, ce qui **égalise** les
deux slots. Contrastes mesurés sur ce fond : initiale à **6,8:1** (seuil AA 4,5:1), chiffre de
statistique à **4,5:1** (texte large, seuil AA 3:1). L'alternative — deux jeux de valeurs pour une
même couleur — aurait divergé au premier ajustement.

Le §8.1 s'applique **sans exception** : l'initiale de chaque parent reste affichée quelle que soit la
palette, et trois tests le vérifient explicitement (web, mobile, E2E sous contraste forcé).

**Portes** : backend **457 tests / 4 074 assertions** (445 avant), couverture **87,3 %**, PHPStan 0,
cs-fixer 0, Twig 38 ; mobile **431 tests**, couverture **83,3 %**, `dart analyze` 0, format 0 ;
**E2E 23/23** (51,9 s). **Contrat OpenAPI inchangé** — E2, E3 et E5 n'exposent aucun champ nouveau,
le client Dart n'a donc pas été régénéré. Aucun champ personnel introduit : le registre est
inchangé. Aucune dépendance ajoutée : `INSTALL.md` est inchangé.
