import { ApiController } from '../api_base.js';

/*
 * Mutations de l'écran Enfants (Lot 2) via l'API REST, scopées X-Family-Id. Le serveur reste
 * l'autorité (Voters). Succès → rechargement (l'état canonique, et les notes live Mercure, suivent).
 */
export default class extends ApiController {
    static targets = ['infoRows'];

    createChild(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const body = {
            firstName: form.querySelector('[name="firstName"]').value,
        };
        const birth = form.querySelector('[name="birthDate"]').value;
        if (birth) body.birthDate = birth;
        this._send('POST', `/api/families/${this.familyIdValue}/children`, body, {
            event,
            notice: 'Profil enfant créé.',
            idempotent: true,
        });
    }

    updateChild(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const id = form.dataset.childId;
        const labels = [...form.querySelectorAll('[name="info_label[]"]')].map((i) => i.value);
        const values = [...form.querySelectorAll('[name="info_value[]"]')].map((i) => i.value);
        const info = labels.map((label, i) => ({ label, value: values[i] || '' })).filter((r) => r.label || r.value);
        const body = {
            firstName: form.querySelector('[name="firstName"]').value,
            color: form.querySelector('[name="color"]').value,
            birthDate: form.querySelector('[name="birthDate"]').value || null,
            schoolLabel: form.querySelector('[name="schoolLabel"]').value,
            info,
        };
        this._send('PATCH', `/api/children/${id}`, body, { event, notice: 'Fiche enregistrée.' });
    }

    addInfoRow(event) {
        event.preventDefault();
        const row = document.createElement('div');
        row.className = 'info-row';
        row.innerHTML =
            '<input name="info_label[]" placeholder="Libellé" aria-label="Libellé de l\'information">' +
            '<input name="info_value[]" placeholder="Valeur" aria-label="Valeur de l\'information">';
        this.infoRowsTarget.appendChild(row);
    }

    deleteChild(event) {
        if (!window.confirm('Supprimer ce profil enfant et ses notes ? Cette action est définitive.')) return;
        this._send('DELETE', `/api/children/${event.currentTarget.dataset.childId}`, null, {
            event,
            notice: 'Profil enfant supprimé.',
        });
    }

    addNote(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const text = form.querySelector('[name="text"]').value;
        this._send('POST', `/api/children/${form.dataset.childId}/notes`, { text }, {
            event,
            notice: 'Note publiée.',
            idempotent: true,
        });
    }

    /** La suppression revient à la liste ; une création/édition recharge en ciblant l'enfant concerné. */
    _onSuccess(data, method, url) {
        if ('DELETE' === method && url.includes('/children/')) {
            window.location.assign('/children');
            return;
        }
        const childId = data.id || data.childId;
        const targetsChild = childId && url.includes('/children') && !url.includes('/notes');
        window.location.assign(targetsChild ? `/children?child=${childId}` : window.location.href);
    }
}
