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
 *  - le rattrapage du 401 : l'access token vit 15 min, le refresh 30 jours — on rejoue une fois
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
     */
    async _send(method, url, body, opts = {}) {
        const trigger = this._triggerOf(opts.event);
        // Une même clé pour l'appel initial ET le rejeu après refresh : c'est tout l'intérêt.
        const idempotencyKey = opts.idempotent ? this._uuid() : null;

        if (this.hasErrorTarget) this.errorTarget.hidden = true;
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
                if (opts.notice) this._storeNotice(opts.notice);
                const data = await response.json().catch(() => ({}));
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

    _storeNotice(message) {
        try {
            window.sessionStorage.setItem(NOTICE_KEY, message);
        } catch (e) {
            /* stockage indisponible (navigation privée stricte) : on renonce au message, pas à l'action */
        }
    }

    _flushNotice() {
        if (!this.hasNoticeTarget) return;
        let message = null;
        try {
            message = window.sessionStorage.getItem(NOTICE_KEY);
            if (message) window.sessionStorage.removeItem(NOTICE_KEY);
        } catch (e) {
            return;
        }
        if (!message) return;
        this.noticeTarget.textContent = message;
        this.noticeTarget.hidden = false;
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
