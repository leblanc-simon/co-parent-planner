import { ApiController } from '../api_base.js';

/*
 * Mutations de l'écran Agenda (Lot 4) via l'API REST, scopées X-Family-Id. Succès → rechargement
 * (état canonique ; le temps réel Mercure rafraîchit aussi chez les autres membres).
 * Les datetime-local (heure murale locale) sont convertis en ISO 8601 avec offset via Date.toISOString().
 */
export default class extends ApiController {
    static targets = ['wholeFamily', 'children', 'allDay', 'startTimed', 'endTimed', 'startAllDay', 'freq', 'recurrence'];
    static values = { timezone: String };

    connect() {
        super.connect();
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
        const description = f.querySelector('[name="description"]');
        if (description) body.description = description.value || null;
        this._send('POST', `/api/families/${this.familyIdValue}/events`, body, {
            event,
            notice: 'Événement créé.',
            idempotent: true,
        });
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
        // `description` n'est envoyé QUE si le formulaire porte le champ : un PATCH partiel avec une
        // chaîne vide effacerait le commentaire existant (la saisie du commentaire reste à livrer).
        const description = f.querySelector('[name="description"]');
        if (description) body.description = description.value;
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
        this._send('PATCH', url, body, { event, notice: 'Événement enregistré.' });
    }

    /**
     * Suppression : la portée est rappelée dans la demande de confirmation, car « Toute la série »
     * détruit l'occurrence pivot ET toutes les suivantes de façon définitive.
     */
    deleteEvent(event) {
        const f = event.currentTarget.closest('form');
        const recurring = f.dataset.recurring === '1';
        const scope = recurring ? (f.querySelector('[name="scope"]')?.value || 'occurrence') : 'occurrence';
        const title = f.querySelector('[name="title"]')?.value?.trim() || 'cet événement';

        const question = recurring && 'series' === scope
            ? `Supprimer « ${title} » ET toutes les occurrences suivantes de la série ? Cette action est définitive.`
            : `Supprimer « ${title} » ? Cette action est définitive.`;
        if (!window.confirm(question)) return;

        let url = `/api/events/${f.dataset.eventId}`;
        if (recurring) {
            url += 'occurrence' === scope
                ? `?scope=occurrence&recurrenceDate=${f.dataset.recurrenceDate}`
                : `?scope=series&from=${f.dataset.recurrenceDate}`;
        }
        this._send('DELETE', url, null, {
            event,
            notice: 'series' === scope ? 'Série supprimée à partir de cette date.' : 'Événement supprimé.',
        });
    }

    // Overlay famille liée (US-AG-4) : persiste la préférence du membre puis recharge.
    toggleOverlay(event) {
        const el = event.currentTarget;
        this._send('PUT', `/api/families/${this.familyIdValue}/overlay-preferences`, {
            familyLinkId: el.dataset.linkId,
            view: el.dataset.view || 'agenda',
            enabled: el.checked,
        }, { event });
    }

    _val(form, name) {
        const el = form.querySelector(`[name="${name}"]`);
        return el ? el.value : '';
    }

    _iso(localValue) {
        return localValue ? new Date(localValue).toISOString() : null;
    }
}
