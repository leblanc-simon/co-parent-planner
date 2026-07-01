import { Controller } from '@hotwired/stimulus';

/*
 * Mutations de l'écran Garde (Lot 3) via l'API REST, scopées X-Family-Id. Succès → rechargement
 * (état canonique ; la génération est asynchrone côté serveur, et le temps réel Mercure rafraîchit).
 */
export default class extends Controller {
    static targets = ['error', 'kind', 'customGrid'];
    static values = { familyId: String, parentA: String, parentB: String };

    connect() {
        this.toggleCustom();
    }

    toggleCustom() {
        if (!this.hasCustomGridTarget || !this.hasKindTarget) return;
        this.customGridTarget.hidden = this.kindTarget.value !== 'custom';
    }

    savePattern(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const body = {
            kind: form.querySelector('[name="kind"]').value,
            anchorDate: form.querySelector('[name="anchorDate"]').value,
            handoverWeekday: parseInt(form.querySelector('[name="handoverWeekday"]').value, 10),
            handoverTime: form.querySelector('[name="handoverTime"]').value,
            parentAMembershipId: form.querySelector('[name="parentAMembershipId"]').value,
            parentBMembershipId: form.querySelector('[name="parentBMembershipId"]').value,
        };
        if (body.kind === 'custom') {
            body.customPattern = [...form.querySelectorAll('[name="custom[]"]')].map((s) => s.value);
        }
        this._send('PUT', `/api/families/${this.familyIdValue}/custody/pattern`, body);
    }

    // « Échanger ce jour » : crée une exception d'un jour vers l'autre parent.
    swapDay(event) {
        event.preventDefault(); // évite le défilement sur la touche Espace
        const date = event.currentTarget.dataset.date;
        const target = event.currentTarget.dataset.slot === 'A' ? this.parentBValue : this.parentAValue;
        if (!date || !target) return;
        this._send('POST', `/api/families/${this.familyIdValue}/custody/exceptions`, {
            startDate: date,
            endDate: date,
            parentMembershipId: target,
            reason: 'Échange',
        });
    }

    addException(event) {
        event.preventDefault();
        const f = event.currentTarget;
        this._send('POST', `/api/families/${this.familyIdValue}/custody/exceptions`, {
            startDate: f.querySelector('[name="startDate"]').value,
            endDate: f.querySelector('[name="endDate"]').value,
            parentMembershipId: f.querySelector('[name="parentMembershipId"]').value,
            reason: f.querySelector('[name="reason"]').value || null,
        });
    }

    deleteException(event) {
        this._send('DELETE', `/api/families/${this.familyIdValue}/custody/exceptions/${event.currentTarget.dataset.exceptionId}`);
    }

    addHoliday(event) {
        event.preventDefault();
        const f = event.currentTarget;
        this._send('POST', `/api/families/${this.familyIdValue}/holidays`, {
            label: f.querySelector('[name="label"]').value,
            startDate: f.querySelector('[name="startDate"]').value,
            endDate: f.querySelector('[name="endDate"]').value,
        });
    }

    importHolidays() {
        this._send('POST', `/api/families/${this.familyIdValue}/holidays/import`, null);
    }

    saveRule(event) {
        event.preventDefault();
        const f = event.currentTarget;
        const kind = f.querySelector('[name="kind"]').value;
        const parent = f.querySelector('[name="parent"]').value;
        const other = parent === 'A' ? 'B' : 'A';
        const params = {
            alternating_weeks: { startParent: parent },
            split_50_50: { firstHalf: parent, secondHalf: other },
            fixed_parent: { parent },
            eve_alternating: { evenParent: parent },
        }[kind] || {};
        this._send('PUT', `/api/holidays/${f.dataset.holidayId}/rule`, { kind, params });
    }

    // Overlay famille liée (US-CUST-5) : persiste la préférence garde du membre puis recharge.
    toggleOverlay(event) {
        const el = event.currentTarget;
        this._send('PUT', `/api/families/${this.familyIdValue}/overlay-preferences`, {
            familyLinkId: el.dataset.linkId,
            view: el.dataset.view || 'custody',
            enabled: el.checked,
        });
    }

    async _send(method, url, body) {
        if (this.hasErrorTarget) this.errorTarget.hidden = true;
        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': method === 'PUT' ? 'application/json' : 'application/json',
                    Accept: 'application/json',
                    'X-Family-Id': this.familyIdValue,
                },
                body: body === null ? undefined : JSON.stringify(body),
                credentials: 'same-origin',
            });
            if (response.ok) {
                window.location.reload();
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
