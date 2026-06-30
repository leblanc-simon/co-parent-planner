<?php

declare(strict_types=1);

namespace App\Service\Custody;

use App\Entity\Family;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publie un signal temps réel de changement de garde sur `/families/{id}/custody` (US-CUST-5,
 * §4.5 cdc). **Best-effort, après commit** : un échec (hub injoignable) est journalisé sans jamais
 * faire échouer la mutation. Mise à jour **privée** (seuls les abonnés autorisés `custody ≥ view`).
 *
 * Le payload est un Turbo Stream `refresh` (Turbo 8 : morphing de la page) ; les clients non-Turbo
 * (mobile) reçoivent le même évènement et rechargent leur fenêtre.
 */
final class CustodyPublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publish(Family $family): void
    {
        try {
            $topic = \sprintf('/families/%s/custody', $family->getId());
            $this->hub->publish(new Update($topic, '<turbo-stream action="refresh"></turbo-stream>', private: true));
        } catch (\Throwable $e) {
            $this->logger->warning('Échec de publication temps réel de la garde.', ['exception' => $e]);
        }
    }
}
