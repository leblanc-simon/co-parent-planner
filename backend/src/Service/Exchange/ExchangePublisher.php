<?php

declare(strict_types=1);

namespace App\Service\Exchange;

use App\Entity\Exchange;
use App\Entity\Family;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Uid\Uuid;

/**
 * Diffuse les signaux temps réel des échanges (cdc §4.4, Lot 6 §3.7) : topic **liste**
 * `/families/{id}/exchanges` (création / clôture / décision → badge + liste) et topic **détail**
 * `/families/{id}/exchanges/{eid}` (message / vote / bascule → fil + options + statut).
 *
 * **Best-effort, après commit** : un échec (hub injoignable) est journalisé sans jamais faire échouer
 * la mutation. Mises à jour **privées** (seuls les abonnés autorisés `exchanges ≥ view`). Charge =
 * Turbo Stream `refresh` (Turbo 8 morphing) ; le mobile reçoit le même signal et recharge sa fenêtre.
 */
final class ExchangePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** Signal sur le topic liste (badge + collection) de la famille. */
    public function publishList(Family $family): void
    {
        $this->publishTopic(\sprintf('/families/%s/exchanges', $family->getId()));
    }

    /** Signal sur le topic détail d'un échange (fil, options, statut). */
    public function publishExchange(Exchange $exchange): void
    {
        $this->publishTopic($this->exchangeTopic($exchange->getFamily()->getId(), $exchange->getId()));
    }

    private function exchangeTopic(Uuid $familyId, Uuid $exchangeId): string
    {
        return \sprintf('/families/%s/exchanges/%s', $familyId, $exchangeId);
    }

    private function publishTopic(string $topic): void
    {
        try {
            $this->hub->publish(new Update($topic, '<turbo-stream action="refresh"></turbo-stream>', private: true));
        } catch (\Throwable $e) {
            $this->logger->warning('Échec de publication temps réel d\'un échange.', ['topic' => $topic, 'exception' => $e]);
        }
    }
}
