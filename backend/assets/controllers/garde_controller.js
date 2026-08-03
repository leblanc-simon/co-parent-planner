import { ApiController } from '../api_base.js';

/*
 * Mutations de l'écran Garde (Lot 3) via l'API REST, scopées X-Family-Id. Succès → rechargement
 * (état canonique ; la génération est asynchrone côté serveur, et le temps réel Mercure rafraîchit).
 */
export default class extends ApiController {
    static targets = ['kind', 'customPanel', 'midweekPanel', 'night', 'mwDay'];
    static values = { parentA: String, parentB: String, paName: String, pbName: String };

    connect() {
        super.connect();
        this.selectKind();
        this.relabelNights();
    }

    // Affiche le panneau de configuration correspondant au rythme coché.
    selectKind() {
        const kind = this._currentKind();
        if (this.hasCustomPanelTarget) this.customPanelTarget.hidden = kind !== 'custom';
        if (this.hasMidweekPanelTarget) this.midweekPanelTarget.hidden = kind !== 'midweek_weekends';
    }

    _currentKind() {
        const checked = this.kindTargets.find((r) => r.checked);
        return checked ? checked.value : 'alternating';
    }

    // Bascule une nuit du motif personnalisé entre parent A et parent B.
    toggleNight(event) {
        const btn = event.currentTarget;
        const input = btn.querySelector('input[name="custom[]"]');
        const next = input.value === 'A' ? 'B' : 'A';
        input.value = next;
        btn.classList.toggle('night--a', next === 'A');
        btn.classList.toggle('night--b', next === 'B');
        btn.querySelector('.night__name').textContent = next === 'B' ? this.pbNameValue : this.paNameValue;
    }

    // Cycle un jour « milieu de semaine » : parent A → parent B → week-end alterné.
    cycleDay(event) {
        const btn = event.currentTarget;
        const next = { A: 'B', B: 'WE', WE: 'A' }[btn.dataset.state] || 'A';
        btn.dataset.state = next;
        btn.classList.remove('night--a', 'night--b', 'night--we');
        btn.classList.add(next === 'WE' ? 'night--we' : next === 'B' ? 'night--b' : 'night--a');
        btn.querySelector('.night__name').textContent =
            next === 'WE' ? 'Week-end' : next === 'B' ? this.pbNameValue : this.paNameValue;
    }

    // Réétiquette les 14 nuits d'après la date d'ancrage (défaut : lundi si vide/invalide).
    relabelNights() {
        if (!this.hasNightTarget) return;
        const dow = ['DIM', 'LUN', 'MAR', 'MER', 'JEU', 'VEN', 'SAM'];
        const anchor = this.element.querySelector('[name="anchorDate"]');
        let start = 1;
        if (anchor && anchor.value) {
            const d = new Date(`${anchor.value}T00:00:00`);
            if (!Number.isNaN(d.getTime())) start = d.getDay();
        }
        this.nightTargets.forEach((btn) => {
            const i = parseInt(btn.dataset.index, 10);
            const label = btn.querySelector('.night__dow');
            if (label) label.textContent = dow[(start + i) % 7];
        });
    }

    savePattern(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const kind = this._currentKind();
        const body = {
            kind,
            anchorDate: form.querySelector('[name="anchorDate"]').value,
            handoverWeekday: parseInt(form.querySelector('[name="handoverWeekday"]').value, 10),
            handoverTime: form.querySelector('[name="handoverTime"]').value,
            parentAMembershipId: form.querySelector('[name="parentAMembershipId"]').value,
            parentBMembershipId: form.querySelector('[name="parentBMembershipId"]').value,
        };
        if (kind === 'custom') {
            body.customPattern = [...form.querySelectorAll('input[name="custom[]"]')].map((s) => s.value);
        } else if (kind === 'midweek_weekends') {
            body.settings = this._midweekSettings();
        }
        this._send('PUT', `/api/families/${this.familyIdValue}/custody/pattern`, body, {
            event,
            notice: 'Rythme enregistré — le planning à venir est régénéré (exceptions et règles de vacances conservées).',
        });
    }

    // Reconstruit l'objet `settings` du rythme milieu-de-semaine depuis les cartes-jour.
    _midweekSettings() {
        const weekdayParents = {};
        const weekendDays = [];
        this.mwDayTargets.forEach((btn) => {
            const w = parseInt(btn.dataset.weekday, 10);
            if (btn.dataset.state === 'WE') weekendDays.push(w);
            else weekdayParents[String(w)] = btn.dataset.state === 'B' ? 'B' : 'A';
        });
        const start = this.element.querySelector('[name="mw_weekend_start"]:checked');
        return {
            weekday_parents: weekdayParents,
            weekend_days: weekendDays,
            weekend_start_parent: start ? start.value : 'A',
        };
    }

    /**
     * « Échanger ce jour » : crée une exception d'un jour vers l'autre parent.
     *
     * Confirmation explicite : un clic (ou Entrée/Espace) sur une case réattribue durablement un jour
     * de garde, et l'annulation ne se fait que depuis Paramètres › Exceptions.
     */
    swapDay(event) {
        event.preventDefault(); // évite le défilement sur la touche Espace
        const cell = event.currentTarget;
        const date = cell.dataset.date;
        const fromA = 'A' === cell.dataset.slot;
        const target = fromA ? this.parentBValue : this.parentAValue;
        if (!date || !target) return;

        const toName = fromA ? this.pbNameValue : this.paNameValue;
        const label = cell.dataset.dateLabel || date;
        if (!window.confirm(`Attribuer le ${label} à ${toName || 'l\'autre parent'} ?`)) return;

        this._send('POST', `/api/families/${this.familyIdValue}/custody/exceptions`, {
            startDate: date,
            endDate: date,
            parentMembershipId: target,
            reason: 'Échange',
        }, {
            event,
            notice: `Le ${label} est désormais attribué à ${toName || 'l\'autre parent'}.`,
            idempotent: true,
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
        }, { event, notice: 'Exception ajoutée.', idempotent: true });
    }

    deleteException(event) {
        const label = event.currentTarget.dataset.exceptionLabel;
        const what = label ? `l'exception ${label}` : 'cette exception';
        if (!window.confirm(`Supprimer ${what} ? Les jours concernés reviendront au rythme habituel.`)) return;
        this._send('DELETE', `/api/families/${this.familyIdValue}/custody/exceptions/${event.currentTarget.dataset.exceptionId}`, null, {
            event,
            notice: 'Exception supprimée.',
        });
    }

    addHoliday(event) {
        event.preventDefault();
        const f = event.currentTarget;
        this._send('POST', `/api/families/${this.familyIdValue}/holidays`, {
            label: f.querySelector('[name="label"]').value,
            startDate: f.querySelector('[name="startDate"]').value,
            endDate: f.querySelector('[name="endDate"]').value,
        }, { event, notice: 'Période de vacances ajoutée.', idempotent: true });
    }

    importHolidays(event) {
        this._send('POST', `/api/families/${this.familyIdValue}/holidays/import`, null, {
            event,
            notice: 'Import lancé — les périodes officielles apparaissent dans quelques instants.',
        });
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
        this._send('PUT', `/api/holidays/${f.dataset.holidayId}/rule`, { kind, params }, {
            event,
            notice: 'Règle de vacances appliquée — le planning de la période est régénéré.',
        });
    }

    // Overlay famille liée (US-CUST-5) : persiste la préférence garde du membre puis recharge.
    toggleOverlay(event) {
        const el = event.currentTarget;
        this._send('PUT', `/api/families/${this.familyIdValue}/overlay-preferences`, {
            familyLinkId: el.dataset.linkId,
            view: el.dataset.view || 'custody',
            enabled: el.checked,
        }, { event });
    }
}
