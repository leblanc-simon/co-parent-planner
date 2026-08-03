import { ApiController } from '../api_base.js';

/* Libellés FR des domaines de droit et des niveaux — mêmes intitulés que la navigation et les
 * gabarits Twig, pour ne jamais exposer les noms techniques (`children_notes`, `view`…). */
const DOMAIN_LABELS = {
    custody: 'Garde',
    agenda: 'Agenda',
    children_notes: 'Enfants & notes',
    exchanges: 'Échanges',
    expenses: 'Dépenses',
    family_settings: 'Réglages de la famille',
};
const LEVEL_LABELS = { none: 'aucun accès', view: 'lecture', edit: 'édition' };

/*
 * Pilote les mutations de l'écran Paramètres › Compte & Famille (Lot 1) en appelant l'API REST,
 * scopées par le header X-Family-Id (famille active). Le serveur reste l'autorité (Voters) ; en cas
 * de succès la page est rechargée pour refléter l'état canonique, sinon l'erreur localisée s'affiche.
 */
export default class extends ApiController {
    static targets = ['summary', 'inviteRole', 'inviteRights'];

    // ---- Réglages famille ----
    saveFamily(event) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        const body = {};
        // Champs requis/non effaçables : envoyés seulement si non vides.
        ['name', 'type', 'timezone'].forEach((f) => {
            const v = data.get(f);
            if (v !== null && v !== '') body[f] = v;
        });
        // Champs optionnels EFFACABLES : un champ vidé est envoyé à null pour effacer la valeur serveur.
        const academy = data.get('academy');
        body.academy = academy === '' || academy === null ? null : academy;
        const zone = data.get('schoolZone');
        body.schoolZone = zone === '' || zone === null ? null : zone;
        // `handoverWeekday`/`handoverTime` ne sont plus saisis ici (revue 6.9 : doublon inerte du
        // § Rythme de garde). Ils ne doivent SURTOUT pas rester dans le corps : le champ ayant
        // disparu du formulaire, `data.get()` renverrait `null` et le processor, qui n'écrit que les
        // clés reçues, effacerait alors la valeur enregistrée en base à chaque sauvegarde des
        // réglages de la famille.
        this._send('PATCH', `/api/families/${this.familyIdValue}`, body, {
            event,
            notice: 'Réglages de la famille enregistrés.',
        });
    }

    // ---- Membres ----
    saveMember(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const id = form.dataset.membershipId;
        // N'envoyer QUE les champs dont le contrôle est activé : un non-admin n'édite que sa couleur,
        // donc role/rights/isAdmin (désactivés) ne doivent pas figurer dans la requête (sinon 403).
        const body = {};
        const role = form.querySelector('[name="role"]');
        if (role && !role.disabled) body.role = role.value;
        const admin = form.querySelector('[name="isAdmin"]');
        if (admin && !admin.disabled) body.isAdmin = admin.checked;
        const color = form.querySelector('[name="displayColor"]');
        if (color && !color.disabled) body.displayColor = color.value;
        const rights = this._rights(form);
        if (Object.keys(rights).length) body.rights = rights;
        this._send('PATCH', `/api/memberships/${id}`, body, { event, notice: 'Droits du membre enregistrés.' });
    }

    removeMember(event) {
        const name = event.currentTarget.dataset.memberName;
        const who = name ? `« ${name} »` : 'ce membre';
        if (!window.confirm(`Retirer ${who} de la famille ? Cette personne perdra tout accès.`)) return;
        this._send('DELETE', `/api/memberships/${event.currentTarget.dataset.membershipId}`, null, {
            event,
            notice: 'Membre retiré de la famille.',
        });
    }

    // ---- Invitations ----
    invite(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const body = {
            email: form.querySelector('[name="email"]').value,
            role: form.querySelector('[name="role"]').value,
            rights: this._rights(form),
        };
        this._send('POST', `/api/families/${this.familyIdValue}/invitations`, body, {
            event,
            notice: 'Invitation envoyée.',
            idempotent: true,
        });
    }

    revokeInvitation(event) {
        const email = event.currentTarget.dataset.invitationEmail;
        const who = email ? `l'invitation de ${email}` : 'cette invitation';
        if (!window.confirm(`Révoquer ${who} ? Le lien reçu par e-mail cessera de fonctionner.`)) return;
        this._send('DELETE', `/api/invitations/${event.currentTarget.dataset.invitationId}`, null, {
            event,
            notice: 'Invitation révoquée.',
        });
    }

    // ---- Liens entre familles ----
    createLink(event) {
        event.preventDefault();
        const linkedFamilyId = event.currentTarget.querySelector('[name="linkedFamilyId"]').value;
        this._send('POST', `/api/families/${this.familyIdValue}/links`, { linkedFamilyId }, {
            event,
            notice: 'Lien proposé — il devient actif quand l\'autre famille l\'accepte.',
        });
    }

    activateLink(event) {
        this._send('POST', `/api/families/${this.familyIdValue}/links/${event.currentTarget.dataset.linkId}/activate`, null, {
            event,
            notice: 'Lien activé.',
        });
    }

    removeLink(event) {
        if (!window.confirm('Supprimer ce lien entre familles ? Les agendas cesseront d\'être partagés.')) return;
        this._send('DELETE', `/api/families/${this.familyIdValue}/links/${event.currentTarget.dataset.linkId}`, null, {
            event,
            notice: 'Lien supprimé.',
        });
    }

    // ---- Récapitulatif des droits avant envoi (CA §3.8) ----
    refreshSummary() {
        if (!this.hasSummaryTarget) return;
        const role = this.hasInviteRoleTarget ? this.inviteRoleTarget.value : 'step_parent';
        if (role === 'parent') {
            this.summaryTarget.textContent = 'Un parent dispose de tous les droits (édition partout).';
            return;
        }
        const parts = [];
        this.inviteRightsTarget.querySelectorAll('select').forEach((sel) => {
            const domain = sel.name.replace(/^rights\[(.+)\]$/, '$1');
            if (sel.value !== 'none') parts.push(`${DOMAIN_LABELS[domain] ?? domain} : ${LEVEL_LABELS[sel.value] ?? sel.value}`);
        });
        this.summaryTarget.textContent = parts.length ? `Droits proposés — ${parts.join(', ')}.` : 'Aucun droit accordé (membre en lecture seule de rien).';
    }

    // ---- Helpers ----
    _rights(form) {
        const rights = {};
        form.querySelectorAll('select[name^="rights["]').forEach((sel) => {
            if (sel.disabled) return;
            const domain = sel.name.replace(/^rights\[(.+)\]$/, '$1');
            rights[domain] = sel.value;
        });
        return rights;
    }
}
