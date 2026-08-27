import { Controller } from '@hotwired/stimulus';

/*
 * Surveille l'abonnement temps réel d'un écran (`<turbo-stream-source>`, Turbo Streams over Mercure)
 * et prévient l'utilisateur quand il ne fonctionne pas.
 *
 * Sans cela, un hub injoignable ou un abonnement refusé se traduisait par une page silencieusement
 * figée : aucune mise à jour n'arrivait et rien ne le disait (revue 2026-08 C11). L'écran reste
 * parfaitement utilisable — seule la synchronisation instantanée manque —, le message invite donc à
 * recharger, jamais à recommencer une action.
 *
 * Le composant de UX Turbo expose son `EventSource` sur l'élément (`es`) : on s'y greffe plutôt que
 * d'ouvrir une seconde connexion, qui doublerait la charge du hub. Si l'implémentation amont change
 * et que la propriété disparaît, le contrôleur ne fait simplement rien (aucune régression d'écran).
 */
export default class extends Controller {
    /** Nombre d'échecs consécutifs (sans `open`) avant d'alerter : une reconnexion réussie est normale. */
    static FAILURES_BEFORE_ALERT = 2;

    connect() {
        this.failures = 0;
        this.alerted = false;
        // <turbo-stream-source> crée son EventSource dans son propre connectedCallback : on laisse
        // passer une tâche pour être indépendant de l'ordre d'initialisation des deux composants.
        this.startTimer = window.setTimeout(() => this.watch(), 0);
    }

    disconnect() {
        window.clearTimeout(this.startTimer);
        const es = this.element.es;
        if (es && this.onOpen) {
            es.removeEventListener('open', this.onOpen);
            es.removeEventListener('error', this.onError);
        }
    }

    watch() {
        const es = this.element.es;
        if (!es) return;

        this.onOpen = () => {
            this.failures = 0;
            if (this.alerted) {
                this.alerted = false;
                window.dispatchEvent(new CustomEvent('cpp:realtime-up'));
            }
        };
        this.onError = () => {
            this.failures += 1;
            // readyState CLOSED (2) : l'EventSource ne retentera pas — inutile d'attendre.
            const definitive = es.readyState === 2;
            if (this.alerted || (!definitive && this.failures < this.constructor.FAILURES_BEFORE_ALERT)) return;
            this.alerted = true;
            window.dispatchEvent(new CustomEvent('cpp:realtime-down'));
        };

        es.addEventListener('open', this.onOpen);
        es.addEventListener('error', this.onError);
    }
}
