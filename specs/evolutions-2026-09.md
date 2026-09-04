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

**4. Notifications : traiter le couple ensemble (coût faible en v1, décision à confirmer).**
Proposition par défaut, à valider : **le périmètre des destinataires ne change pas** — ce sont les
droits qui décident qui reçoit quoi, et les préférences de notification restent par utilisateur
(`NotificationPref`). L'association ne sert qu'à **nommer** le foyer dans le corps de l'e-mail
(« chez Sophie et Bob »). Élargir la diffusion à un conjoint qui n'a pas le droit correspondant
serait une fuite : à ne faire, si vous le souhaitez, que par un droit explicite.

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
- **Palette encadrée, pas de sélecteur libre.** Le contraste du texte de l'initiale sur le fond de
  case doit rester lisible ; un `input[type=color]` laisse choisir un jaune illisible. **Recommandé :
  une palette de 8 teintes validées** (fond doux + bordure + encre), les deux slots devant rester
  distincts (refus, ou avertissement, si l'on choisit la même couleur pour les deux).
- **Dérogation assumée au §8.** Le cdc fixe accent = parent A et positive = parent B. Les rendre
  configurables *localement* est une dérogation : elle doit être **écrite dans le §8** (« palette par
  défaut, surchargeable par spectateur ») avant l'implémentation, sinon la revue graphique suivante
  la signalera comme un écart.
- **Hors-ligne** — la préférence doit être lisible sans réseau (cache de lecture mobile, cookie ou
  cache web), sinon le planning changerait de couleur en mode avion.

### Surfaces à livrer

| Surface | Web | Mobile |
|---|---|---|
| Choix des couleurs | `/garde` (panneau « Couleurs ») ou Paramètres › Garde | écran Garde, feuille de réglages |
| Rendu | variables CSS posées par le gabarit | `custodyPaletteProvider` |
| Contrat | nouvelle ressource `CustodyColorPreference` (GET collection + PUT) | client régénéré |

---

## Découpage proposé

| Lot | Contenu | Dépendances |
|---|---|---|
| **E1** | A — modèle, contrat, règles de validation, registre RGPD, affichage « conjoint·e de X » (web + mobile) | aucune |
| **E2** | A — foyer : fiche de jour, responsable d'événement, préremplissage des droits | E1 |
| **E3** | A — notifications : nommage du foyer (périmètre des destinataires **inchangé**) | E1 |
| **E4** | B — amendement du §8, palette encadrée, modèle + contrat de la préférence | aucune (parallélisable avec E1) |
| **E5** | B — rendu web (variables CSS) et mobile (provider), choix de couleurs, hors-ligne | E4 |

**DoD par lot** (identique aux lots produits) : tests unitaires + fonctionnels API + E2E web
Playwright + tests Flutter, migration fournie, OpenAPI ré-exporté et client Dart régénéré sans drift,
i18n câblée, registre des données à jour, `INSTALL.md` si une dépendance bouge, analyse statique
propre, écrans conformes au §8.

## Questions encore ouvertes

1. **E3 — notifications** : la proposition ci-dessus ne change **pas** qui reçoit les e-mails
   (les droits décident), elle ne fait que nommer le foyer. À confirmer, ou à remplacer par une règle
   explicite de diffusion au conjoint.
2. **E5 — emplacement du choix des couleurs** : dans l'écran Garde (au plus près de ce qu'il repeint)
   ou dans Paramètres › Garde (avec les autres réglages) ?
3. **B — palette** : 8 teintes imposées, ou palette + « autre couleur » libre avec contrôle de
   contraste au moment du choix ?
4. **A — familles liées** : un beau-parent peut aussi être modélisé par une **famille liée** (Lot 5).
   Faut-il, à terme, exprimer aussi le lien « conjoint » à travers un `FamilyLink`, ou l'association
   reste-t-elle strictement interne à une famille ?
