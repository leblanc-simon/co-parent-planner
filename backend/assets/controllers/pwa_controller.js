import { Controller } from '@hotwired/stimulus';

/*
 * PWA (Lot 8 §2.1) : enregistre le service worker, gère la bannière hors-ligne / reconnexion, le
 * prompt d'installation, la mise à jour du SW et le message de blocage des mutations hors-ligne.
 * Libellés passés en data-values (l'UI web du projet est en FR — cohérence Lot 5). Dégrade proprement
 * si le SW n'est pas supporté (contexte non sécurisé) : l'app reste utilisable.
 */
export default class extends Controller {
    static targets = ['banner', 'install'];
    static values = {
        offlineMessage: String,
        onlineMessage: String,
        mutationMessage: String,
        updateMessage: String,
        family: String,
    };

    connect() {
        this.deferredPrompt = null;
        this.updatePending = false;
        this.onOnline = () => this.showOnline();
        this.onOffline = () => this.showOffline();
        this.onBeforeInstall = (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            if (this.hasInstallTarget) this.installTarget.hidden = false;
        };
        window.addEventListener('online', this.onOnline);
        window.addEventListener('offline', this.onOffline);
        window.addEventListener('beforeinstallprompt', this.onBeforeInstall);

        if (!navigator.onLine) this.showOffline();
        this.registerServiceWorker();
    }

    disconnect() {
        window.removeEventListener('online', this.onOnline);
        window.removeEventListener('offline', this.onOffline);
        window.removeEventListener('beforeinstallprompt', this.onBeforeInstall);
    }

    showOffline() {
        this.setBanner(this.offlineMessageValue, 'pwa-banner--offline', true);
    }

    showOnline() {
        this.setBanner(this.onlineMessageValue, 'pwa-banner--online', false);
        window.setTimeout(() => {
            if (navigator.onLine) this.hideBanner();
        }, 3000);
    }

    setBanner(message, modifier, persistent) {
        if (!this.hasBannerTarget) return;
        const banner = this.bannerTarget;
        banner.textContent = message;
        banner.className = `pwa-banner ${modifier}`;
        banner.hidden = false;
        banner.dataset.persistent = persistent ? 'true' : 'false';
    }

    hideBanner() {
        if (this.hasBannerTarget && this.bannerTarget.dataset.persistent !== 'true') {
            this.bannerTarget.hidden = true;
        }
    }

    install() {
        if (!this.deferredPrompt) return;
        this.deferredPrompt.prompt();
        this.deferredPrompt = null;
        if (this.hasInstallTarget) this.installTarget.hidden = true;
    }

    async registerServiceWorker() {
        if (!('serviceWorker' in navigator)) return;
        try {
            const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            this.postFamily();

            registration.addEventListener('updatefound', () => {
                const worker = registration.installing;
                if (!worker) return;
                worker.addEventListener('statechange', () => {
                    if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                        this.showUpdate(registration);
                    }
                });
            });

            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data && event.data.type === 'offline-mutation') {
                    this.setBanner(this.mutationMessageValue, 'pwa-banner--warn', false);
                    window.setTimeout(() => this.hideBanner(), 4000);
                }
            });

            // Recharge UNIQUEMENT lors d'une mise à jour acceptée par l'utilisateur — jamais lors de
            // la prise de contrôle initiale (clients.claim au 1er enregistrement), qui interromprait
            // la page en cours (ex. un formulaire).
            let reloading = false;
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                if (reloading || !this.updatePending) return;
                reloading = true;
                window.location.reload();
            });
        } catch (e) {
            /* SW indisponible (contexte non sécurisé, navigateur ancien) : dégradation silencieuse. */
        }
    }

    postFamily() {
        if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({ type: 'family', family: this.familyValue || '' });
        }
    }

    showUpdate(registration) {
        this.setBanner(this.updateMessageValue, 'pwa-banner--update', true);
        if (!this.hasBannerTarget) return;
        const reload = document.createElement('button');
        reload.type = 'button';
        reload.className = 'pwa-banner__action';
        reload.textContent = 'Recharger';
        reload.addEventListener('click', () => {
            this.updatePending = true; // autorise le reload sur le prochain controllerchange
            if (registration.waiting) registration.waiting.postMessage({ type: 'skip-waiting' });
        });
        this.bannerTarget.appendChild(reload);
    }
}
