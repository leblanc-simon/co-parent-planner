import { Controller } from '@hotwired/stimulus';

/*
 * Mutations de l'écran Enfants (Lot 2) via l'API REST, scopées X-Family-Id. Le serveur reste
 * l'autorité (Voters). Succès → rechargement (l'état canonique, et les notes live Mercure, suivent).
 */
export default class extends Controller {
    static targets = ['error', 'infoRows'];
    static values = { familyId: String };

    createChild(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const body = {
            firstName: form.querySelector('[name="firstName"]').value,
        };
        const birth = form.querySelector('[name="birthDate"]').value;
        if (birth) body.birthDate = birth;
        this._send('POST', `/api/families/${this.familyIdValue}/children`, body);
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
        this._send('PATCH', `/api/children/${id}`, body);
    }

    addInfoRow(event) {
        event.preventDefault();
        const row = document.createElement('div');
        row.className = 'info-row';
        row.innerHTML =
            '<input name="info_label[]" placeholder="Libellé"><input name="info_value[]" placeholder="Valeur">';
        this.infoRowsTarget.appendChild(row);
    }

    deleteChild(event) {
        if (!window.confirm('Supprimer ce profil enfant et ses notes ?')) return;
        this._send('DELETE', `/api/children/${event.currentTarget.dataset.childId}`);
    }

    addNote(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const text = form.querySelector('[name="text"]').value;
        this._send('POST', `/api/children/${form.dataset.childId}/notes`, { text });
    }

    async _send(method, url, body) {
        if (this.hasErrorTarget) this.errorTarget.hidden = true;
        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': method === 'PATCH' ? 'application/merge-patch+json' : 'application/json',
                    Accept: 'application/json',
                    'X-Family-Id': this.familyIdValue,
                },
                body: body ? JSON.stringify(body) : undefined,
                credentials: 'same-origin',
            });
            if (response.ok) {
                if (method === 'DELETE' && url.includes('/children/')) {
                    window.location.assign('/children');
                    return;
                }
                const data = await response.json().catch(() => ({}));
                // Sur création/sélection, recharge en ciblant l'enfant concerné.
                const childId = data.id || data.childId;
                window.location.assign(childId && url.includes('/children') && !url.includes('/notes') ? `/children?child=${childId}` : window.location.href);
                return;
            }
            const data = await response.json().catch(() => ({}));
            this._showError(data.detail || data['hydra:description'] || 'Une erreur est survenue.');
        } catch (e) {
            this._showError('Connexion impossible. Réessayez.');
        }
    }

    _showError(message) {
        if (!this.hasErrorTarget) return;
        this.errorTarget.textContent = message;
        this.errorTarget.hidden = false;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}
