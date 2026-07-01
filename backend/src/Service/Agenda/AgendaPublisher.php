<?php

declare(strict_types=1);

namespace App\Service\Agenda;

use App\Entity\Family;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publie un signal temps réel de changement d'agenda sur `/families/{id}/agenda` (US-AG-8, §4.5 cdc).
 * **Best-effort, après commit** : un échec (hub injoignable) est journalisé sans jamais faire échouer
 * la mutation. Mise à jour **privée** (seuls les abonnés autorisés `agenda ≥ view`).
 *
 * Payload = Turbo Stream `refresh` (Turbo 8 morphing) ; les clients non-Turbo (mobile) reçoivent le
 * même évènement et rechargent leur fenêtre.
 */
final class AgendaPublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publish(Family $family): void
    {
        try {
            $topic = \sprintf('/families/%s/agenda', $family->getId());
            $this->hub->publish(new Update($topic, '<turbo-stream action="refresh"></turbo-stream>', private: true));
        } catch (\Throwable $e) {
            $this->logger->warning('Échec de publication temps réel de l\'agenda.', ['exception' => $e]);
        }
    }
}
