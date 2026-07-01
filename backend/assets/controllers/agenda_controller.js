import { Controller } from '@hotwired/stimulus';

/*
 * Mutations de l'écran Agenda (Lot 4) via l'API REST, scopées X-Family-Id. Succès → rechargement
 * (état canonique ; le temps réel Mercure rafraîchit aussi chez les autres membres).
 * Les datetime-local (heure murale locale) sont convertis en ISO 8601 avec offset via Date.toISOString().
 */
export default class extends Controller {
    static targets = ['error', 'wholeFamily', 'children', 'allDay', 'startTimed', 'endTimed', 'startAllDay', 'freq', 'recurrence'];
    static values = { familyId: String, timezone: String };

    connect() {
        this.toggleAllDay();
        this.toggleRecurrence();
        this.toggleWholeFamily();
    }

    toggleAllDay() {
        if (!this.hasAllDayTarget) return;
        const allDay = this.allDayTarget.checked;
        [this.startTimedTarget, this.endTimedTarget].forEach((el) => { el.hidden = allDay; });
        this.startAllDayTarget.hidden = !allDay;
    }

    toggleRecurrence() {
        if (!this.hasFreqTarget || !this.hasRecurrenceTarget) return;
        this.recurrenceTarget.hidden = this.freqTarget.value === 'none';
    }

    toggleWholeFamily() {
        if (!this.hasWholeFamilyTarget || !this.hasChildrenTarget) return;
        const whole = this.wholeFamilyTarget.checked;
        this.childrenTarget.style.opacity = whole ? '.4' : '1';
        this.childrenTarget.querySelectorAll('input').forEach((c) => { c.disabled = whole; });
    }

    createEvent(event) {
        event.preventDefault();
        const f = event.currentTarget;
        const allDay = f.querySelector('[name="allDay"]').checked;
        const freq = f.querySelector('[name="freq"]').value;
        const body = {
            title: this._val(f, 'title'),
            category: this._val(f, 'category'),
            responsibleMembershipId: this._val(f, 'responsibleMembershipId'),
            wholeFamily: f.querySelector('[name="wholeFamily"]').checked,
            childIds: [...f.querySelectorAll('[name="childIds[]"]:checked')].map((c) => c.value),
            location: this._val(f, 'location') || null,
            allDay,
        };
        if (allDay) {
            body.startsAt = f.querySelector('[name="startsAtDay"]').value;
        } else {
            body.startsAt = this._iso(f.querySelector('[name="startsAt"]').value);
            const end = f.querySelector('[name="endsAt"]').value;
            body.endsAt = end ? this._iso(end) : null;
        }
        if (freq !== 'none') {
            const until = f.querySelector('[name="until"]').value;
            const count = f.querySelector('[name="count"]').value;
            const rec = { freq, excludeHolidays: f.querySelector('[name="excludeHolidays"]').checked };
            if (count) rec.count = parseInt(count, 10);
            else if (until) rec.until = until;
            body.recurrence = rec;
        }
        this._send('POST', `/api/families/${this.familyIdValue}/events`, body);
    }

    saveEvent(event) {
        event.preventDefault();
        const f = event.currentTarget;
        const recurring = f.dataset.recurring === '1';
        const body = {
            title: this._val(f, 'title'),
            category: this._val(f, 'category'),
            location: this._val(f, 'location') || null,
        };
        const start = f.querySelector('[name="startsAt"]');
        if (start) {
            body.startsAt = this._iso(start.value);
            const end = f.querySelector('[name="endsAt"]');
            body.endsAt = end && end.value ? this._iso(end.value) : null;
        }
        let url = `/api/events/${f.dataset.eventId}`;
        if (recurring) {
            body.recurrenceDate = f.dataset.recurrenceDate;
            url += `?scope=${f.querySelector('[name="scope"]')?.value || 'occurrence'}`;
        }
        this._send('PATCH', url, body);
    }

    deleteEvent(event) {
        const f = event.currentTarget.closest('form');
        let url = `/api/events/${f.dataset.eventId}`;
        if (f.dataset.recurring === '1') {
            const scope = f.querySelector('[name="scope"]')?.value || 'occurrence';
            url += 'occurrence' === scope
                ? `?scope=occurrence&recurrenceDate=${f.dataset.recurrenceDate}`
                : `?scope=series&from=${f.dataset.recurrenceDate}`;
        }
        this._send('DELETE', url, null);
    }

    _val(form, name) {
        const el = form.querySelector(`[name="${name}"]`);
        return el ? el.value : '';
    }

    _iso(localValue) {
        return localValue ? new Date(localValue).toISOString() : null;
    }

    async _send(method, url, body) {
        if (this.hasErrorTarget) this.errorTarget.hidden = true;
        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'PATCH' === method ? 'application/merge-patch+json' : 'application/json',
                    Accept: 'application/json',
                    'X-Family-Id': this.familyIdValue,
                },
                body: null === body ? undefined : JSON.stringify(body),
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
