import { ApiController } from '../api_base.js';

/*
 * Mutations de l'écran Garde (Lot 3) via l'API REST, scopées X-Family-Id. Succès → rechargement
 * (état canonique ; la génération est asynchrone côté serveur, et le temps réel Mercure rafraîchit).
 */
export default class extends ApiController {
    static targets = ['kind', 'customPanel', 'midweekPanel', 'night', 'mwDay', 'parentA', 'parentB', 'dayDialog', 'dayFrame'];
    static values = { parentA: String, parentB: String, paName: String, pbName: String };

    connect() {
        super.connect();
        this.selectKind();
        this.relabelNights();
        this.syncParents();
    }

    /**
     * Maintient « Parent A » et « Parent B » sur deux membres distincts.
     *
     * Sans rythme enregistré, les deux sélecteurs retombaient sur la même première option et
     * l'enregistrement échouait forcément (l'API exige deux parents distincts) — revue 2026-08 C13.
     * Le gabarit pré-sélectionne désormais deux membres différents ; ici on empêche l'utilisateur de
     * les faire converger : l'option retenue d'un côté est désactivée de l'autre, et si les deux
     * pointent le même membre, le second bascule sur le premier choix encore libre.
     */
    syncParents(event) {
        if (!this.hasParentATarget || !this.hasParentBTarget) return;
        const changed = event?.currentTarget;
        // Celui qu'on vient de changer garde son choix ; c'est l'autre qui cède.
        const [fixed, other] = changed === this.parentBTarget
            ? [this.parentBTarget, this.parentATarget]
            : [this.parentATarget, this.parentBTarget];

        if (fixed.value && other.value === fixed.value) {
            const free = [...other.options].find((o) => o.value !== fixed.value);
            if (free) other.value = free.value;
        }

        this._disableTaken(this.parentATarget, this.parentBTarget.value);
        this._disableTaken(this.parentBTarget, this.parentATarget.value);
    }

    _disableTaken(select, takenValue) {
        [...select.options].forEach((option) => {
            option.disabled = Boolean(takenValue) && option.value === takenValue;
        });
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
            dayStartTime: form.querySelector('[name="dayStartTime"]').value,
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
     * Ouvre la fiche d'un jour (US-CUST-6) : charge le fragment serveur dans le `<turbo-frame>` de
     * la modale, puis ouvre la `<dialog>` (fond modal, piège à focus et Échap fournis par le
     * navigateur).
     *
     * Le fragment n'est re-demandé que si le jour change : réafficher deux fois le même jour ne
     * doit pas rappeler le serveur, mais passer d'un jour à l'autre doit remettre l'état de
     * chargement, sinon la fiche précédente reste à l'écran pendant la requête et se lit comme
     * étant celle du jour qu'on vient de cliquer.
     */
    openDay(event) {
        event.preventDefault(); // évite le défilement sur la touche Espace
        const url = event.currentTarget.dataset.dayUrl;
        if (!url || !this.hasDayDialogTarget || !this.hasDayFrameTarget) return;

        const frame = this.dayFrameTarget;
        if (frame.dataset.loadedUrl !== url) {
            frame.innerHTML = '<p class="day-modal__loading">Chargement…</p>';
            frame.dataset.loadedUrl = url;
            frame.src = url;
        }
        this.dayDialogTarget.showModal();
    }

    closeDay() {
        if (this.hasDayDialogTarget) this.dayDialogTarget.close();
    }

    /**
     * Clic sur le fond modal → fermeture. La cible n'est le `<dialog>` lui-même que pour la zone
     * hors panneau : un clic dans la fiche a pour cible un de ses descendants.
     */
    dayBackdrop(event) {
        if (event.target === this.dayDialogTarget) this.closeDay();
    }

    /**
     * « Échanger ce jour » : crée une exception d'un jour vers l'autre parent.
     *
     * Déclenché depuis la fiche de jour (bouton explicite sous `custody=edit`), et non plus par un
     * clic sur la case du calendrier — qui réattribuait durablement un jour de garde derrière un
     * simple `confirm()`, y compris par mégarde en parcourant le planning au clavier.
     */
    /**
     * Échange d'un jour depuis sa fiche : le formulaire porte le créneau — jour de début (la veille
     * ou le jour même) et heure, jour de fin (le jour même ou le lendemain) et heure. Les valeurs
     * proposées viennent du serveur, qui seul connaît le rythme et l'heure de début de journée de
     * la famille (cdc §3.4) ; l'utilisateur les corrige avant de confirmer.
     */
    swapDay(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const value = (name) => form.querySelector(`[name="${name}"]`).value;
        const label = form.dataset.dateLabel || 'ce jour';
        const toName = form.dataset.targetName || "l'autre parent";

        this._send('POST', `/api/families/${this.familyIdValue}/custody/exceptions`, {
            startDate: value('startDate'),
            startTime: value('startTime'),
            endDate: value('endDate'),
            endTime: value('endTime'),
            parentMembershipId: value('parentMembershipId'),
            reason: 'Échange',
        }, {
            event,
            notice: `Le ${label} est échangé : les enfants sont chez ${toName} du ${this._moment(value('startDate'), value('startTime'))} au ${this._moment(value('endDate'), value('endTime'))}.`,
            idempotent: true,
        });
    }

    /** « 2026-09-25 » + « 18:00 » → « 25/09 à 18:00 », pour une confirmation qui se relit. */
    _moment(date, time) {
        const [year, month, day] = String(date).split('-');
        return year ? `${day}/${month} à ${time}` : `${date} ${time}`;
    }

    /**
     * Ré-échange d'un jour déjà échangé : l'exception d'un jour est RETIRÉE, le jour revient au
     * rythme habituel (ou à la règle de vacances). Créer une exception en sens inverse en aurait
     * laissé deux sur la même date — c'est le moteur, non l'utilisateur, qui aurait tranché.
     */
    revertDay(event) {
        event.preventDefault();
        const btn = event.currentTarget;
        const label = btn.dataset.dateLabel || 'ce jour';
        if (!window.confirm(`Annuler l'échange du ${label} ? Le jour reviendra au rythme habituel.`)) return;

        this._send('DELETE', `/api/families/${this.familyIdValue}/custody/exceptions/${btn.dataset.exceptionId}`, null, {
            event,
            notice: `L'échange du ${label} est annulé — le jour revient au rythme habituel.`,
        });
    }

    addException(event) {
        event.preventDefault();
        const f = event.currentTarget;
        // Heures laissées vides = journées entières : on envoie `null`, pas une chaîne vide, pour
        // que le serveur lise bien « pas d'heure » et non « heure illisible » (422).
        const time = (name) => f.querySelector(`[name="${name}"]`).value || null;
        this._send('POST', `/api/families/${this.familyIdValue}/custody/exceptions`, {
            startDate: f.querySelector('[name="startDate"]').value,
            startTime: time('startTime'),
            endDate: f.querySelector('[name="endDate"]').value,
            endTime: time('endTime'),
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
