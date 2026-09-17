import { Controller } from '@hotwired/stimulus';

/*
 * Socle commun aux contrôleurs Stimulus qui mutent l'état via l'API REST (décision D2 : le web
 * consomme sa propre API ; les cookies HttpOnly sont posés par le serveur).
 *
 * Cette classe existe parce que six contrôleurs (`children`, `garde`, `agenda`, `exchanges`,
 * `settings`, `notif_prefs`) portaient la même implémentation copiée-collée de `_send()`, et donc
 * les mêmes trous. Elle centralise :
 *
 *  - l'état « occupé » du déclencheur (bouton désactivé + `aria-busy`) → plus de double soumission,
 *    qui produisait des doublons irréversibles (aucune suppression de note ni de message côté web) ;
 *  - le rattrapage du 401 : l'access token vit 1 h, le refresh 30 jours — on rejoue une fois
 *    après `POST /api/auth/refresh` (la saisie est donc conservée), et on ne redirige vers /login
 *    que si le rafraîchissement échoue vraiment ;
 *  - la confirmation de succès, transportée par `sessionStorage` pour survivre au rechargement
 *    qui suit la mutation (le serveur reste l'autorité : on relit toujours l'état canonique) ;
 *  - l'affichage de l'erreur : les violations de validation sont préférées au `detail` RFC 7807
 *    (qui préfixe le chemin de propriété), et le bandeau est amené à l'écran par `scrollIntoView`
 *    — un `scrollTo(top: 0)` renvoyait en haut d'une page où le bandeau, situé plus bas, restait
 *    invisible, faisant passer un échec pour un silence ;
 *  - l'`Idempotency-Key` que le serveur sait déjà lire (§6.3) et que le web n'envoyait jamais.
 *
 * Les sous-classes vivent dans `controllers/` (seul dossier scanné par stimulus-bundle) ; ce fichier
 * n'est donc jamais enregistré comme contrôleur. Une sous-classe qui définit `connect()` doit
 * appeler `super.connect()` pour que la confirmation de succès en attente s'affiche.
 */

const NOTICE_KEY = 'cpp.notice';

/*
 * Durée pendant laquelle une confirmation de succès reste « à afficher ».
 *
 * Elle n'est pas cosmétique : sur un écran temps réel, la mutation qu'on vient de réussir se pousse
 * à soi-même (Mercure → `<turbo-stream action="refresh">`), et cette poussée arrive typiquement
 * APRÈS le rechargement déclenché par `_onSuccess()`. La visite Turbo remplace alors le corps de la
 * page — confirmation comprise — alors que la valeur avait déjà été retirée du stockage par le
 * premier affichage : le message disparaissait aussitôt, ou n'apparaissait jamais. Le conserver
 * quelques secondes le rend re-affichable par le rendu suivant, quel qu'il soit.
 */
const NOTICE_TTL_MS = 5_000;

export class ApiController extends Controller {
    // Stimulus fusionne les `targets`/`values` statiques le long de la chaîne de prototypes (3.x) :
    // les sous-classes déclarent seulement les leurs.
    static targets = ['error', 'notice'];
    static values = { familyId: String };

    connect() {
        this._flushNotice();
    }

    /**
     * Mutation JSON de l'API.
     *
     * @param {string} method
     * @param {string} url
     * @param {object|null} body      corps JSON, ou null pour ne rien envoyer (DELETE)
     * @param {object} opts
     * @param {Event}  [opts.event]      événement d'origine — sert à trouver le déclencheur à désactiver
     * @param {string} [opts.notice]     message de succès affiché après le rechargement
     * @param {boolean}[opts.idempotent] ajoute un `Idempotency-Key` (créations rejouables)
     * @param {(data: object) => void} [opts.onSuccess] traitement du corps de réponse **à la place**
     *        du rechargement — pour les mutations dont le résultat s'affiche sans relire la page
     *        (statut d'un export RGPD, par exemple).
     */
    async _send(method, url, body, opts = {}) {
        const trigger = this._triggerOf(opts.event);
        // Une même clé pour l'appel initial ET le rejeu après refresh : c'est tout l'intérêt.
        const idempotencyKey = opts.idempotent ? this._uuid() : null;

        if (this.hasErrorTarget) this.errorTarget.hidden = true;
        // La confirmation de l'action PRÉCÉDENTE disparaît dès qu'une nouvelle part : la laisser
        // affichée à côté d'un geste en cours laisse croire qu'il a déjà abouti — et, côté tests,
        // rendait « attendre la confirmation » satisfiable par un message périmé. Elle est aussi
        // oubliée du stockage, sinon le rendu suivant la ressusciterait.
        if (this.hasNoticeTarget) this.noticeTarget.hidden = true;
        this._forgetNotice();
        this._busy(trigger, true);

        try {
            let response = await this._fetch(method, url, body, idempotencyKey);

            if (401 === response.status && (await this._refreshSession())) {
                response = await this._fetch(method, url, body, idempotencyKey);
            }
            if (401 === response.status) {
                // La page en cours est conservée : après reconnexion, on revient là où
                // la session a expiré au lieu d'être renvoyé à l'accueil (constat 6.8).
                const next = encodeURIComponent(window.location.pathname + window.location.search);
                window.location.assign(`/login?expired=1&next=${next}`);
                return;
            }

            if (response.ok) {
                const data = await response.json().catch(() => ({}));
                if (opts.onSuccess) {
                    // Pas de rechargement : la confirmation s'affiche ici même, elle n'a donc pas
                    // besoin de survivre à une navigation.
                    if (opts.notice) this._showNotice(opts.notice);
                    opts.onSuccess(data);
                    return;
                }
                if (opts.notice) this._storeNotice(opts.notice);
                this._onSuccess(data, method, url);
                return;
            }

            const data = await response.json().catch(() => ({}));
            this._showError(this._errorMessage(data));
        } catch (e) {
            this._showError('Connexion impossible. Réessayez.');
        } finally {
            this._busy(trigger, false);
        }
    }

    /** Comportement de succès par défaut : relire l'état canonique. Surchargeable. */
    _onSuccess() {
        window.location.reload();
    }

    _fetch(method, url, body, idempotencyKey) {
        const headers = {
            'Content-Type': 'PATCH' === method ? 'application/merge-patch+json' : 'application/json',
            Accept: 'application/json',
        };
        // Les mutations scopées famille l'exigent ; les préférences utilisateur n'ont pas de scope.
        if (this.familyIdValue) headers['X-Family-Id'] = this.familyIdValue;
        if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;

        return fetch(url, {
            method,
            headers,
            body: null === body || undefined === body ? undefined : JSON.stringify(body),
            credentials: 'same-origin',
        });
    }

    /** Rotation du refresh token (cookie HttpOnly de chemin /api/auth/refresh). */
    async _refreshSession() {
        try {
            const response = await fetch('/api/auth/refresh', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: '{}',
                credentials: 'same-origin',
            });

            return response.ok;
        } catch (e) {
            return false;
        }
    }

    /**
     * Les erreurs de validation portent des violations déjà localisées ; le `detail` RFC 7807 y
     * préfixe le chemin de propriété (« firstName: … »), inutile pour l'utilisateur.
     */
    _errorMessage(data) {
        if (Array.isArray(data.violations) && data.violations.length > 0) {
            const messages = data.violations.map((v) => v.message).filter(Boolean);
            if (messages.length > 0) return messages.join(' ');
        }

        return data.detail || data['hydra:description'] || 'Une erreur est survenue.';
    }

    _showError(message) {
        if (!this.hasErrorTarget) return;
        this.errorTarget.textContent = message;
        this.errorTarget.hidden = false;
        this.errorTarget.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    // ---- Confirmation de succès (survit au rechargement) ----

    /**
     * La confirmation est bornée dans le temps, à l'écran ET au contrôleur qui l'a produite.
     *
     * Le contrôleur compte parce que `/settings` en porte quatre, chacun avec sa propre zone de
     * confirmation : sans lui, « Invitation envoyée. » s'afficherait aussi dans les panneaux
     * Notifications et Garde. L'ancienne version s'en tirait par accident — le premier contrôleur
     * connecté consommait le message, fût-ce le mauvais.
     */
    _storeNotice(message) {
        try {
            window.sessionStorage.setItem(
                NOTICE_KEY,
                JSON.stringify({
                    message,
                    url: this._screen(),
                    controller: this.identifier,
                    until: Date.now() + NOTICE_TTL_MS,
                }),
            );
        } catch (e) {
            /* stockage indisponible (navigation privée stricte) : on renonce au message, pas à l'action */
        }
    }

    _forgetNotice() {
        try {
            window.sessionStorage.removeItem(NOTICE_KEY);
        } catch (e) {
            /* rien à oublier si le stockage est indisponible */
        }
    }

    /**
     * Écran destinataire d'une confirmation : le **chemin**, sans la chaîne de requête.
     *
     * Le chemin seul, parce que plusieurs mutations changent la requête en réussissant — créer un
     * enfant redirige vers `/children?child=<id>` — et que la confirmation doit les suivre. La
     * contrepartie est mince et bornée par {@link NOTICE_TTL_MS} : passer d'un enfant à l'autre dans
     * les secondes qui suivent réaffiche le message, jusqu'à ce qu'un nouveau geste l'efface.
     */
    _screen() {
        return window.location.pathname;
    }

    _showNotice(message) {
        if (!this.hasNoticeTarget) return;
        this.noticeTarget.textContent = message;
        this.noticeTarget.hidden = false;
    }

    /**
     * Ré-affiche la confirmation en attente, sans la consommer : le même écran peut être rendu
     * plusieurs fois de suite (rechargement, puis visite Turbo déclenchée par la poussée temps réel),
     * et c'est le dernier rendu qui doit la porter. Elle est oubliée quand elle a expiré, ou dès que
     * l'écran change — sans quoi un « Enregistré » suivrait l'utilisateur sur la page suivante.
     */
    _flushNotice() {
        if (!this.hasNoticeTarget) return;
        let stored = null;
        try {
            stored = window.sessionStorage.getItem(NOTICE_KEY);
        } catch (e) {
            return;
        }
        if (!stored) return;

        let pending = null;
        try {
            pending = JSON.parse(stored);
        } catch (e) {
            this._forgetNotice(); // valeur d'une version antérieure : on ne la devine pas
            return;
        }

        if (!pending?.message || Date.now() > pending.until || pending.url !== this._screen()) {
            this._forgetNotice();
            return;
        }
        // Un autre contrôleur du même écran ne s'en saisit pas — et ne la fait donc pas disparaître.
        if (pending.controller !== this.identifier) return;

        this._showNotice(pending.message);
    }

    // ---- État « occupé » ----

    /** Bouton de soumission d'un formulaire, ou l'élément cliqué/basculé lui-même. */
    _triggerOf(event) {
        const el = event?.currentTarget;
        if (!el) return null;
        if ('FORM' === el.tagName) {
            return el.querySelector('button[type="submit"], button:not([type])');
        }

        return el.matches('button, a, input, select') ? el : null;
    }

    _busy(el, busy) {
        if (!el) return;
        if ('A' === el.tagName) {
            el.setAttribute('aria-busy', busy ? 'true' : 'false');
            return;
        }
        el.disabled = busy;
        if (busy) el.setAttribute('aria-busy', 'true');
        else el.removeAttribute('aria-busy');
    }

    _uuid() {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();

        return `${Date.now().toString(16)}-${Math.random().toString(16).slice(2)}`;
    }
}
