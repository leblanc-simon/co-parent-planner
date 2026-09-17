<?php

declare(strict_types=1);

namespace App\Service\Expense;

use App\Entity\Family;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publie un signal temps réel de changement de dépenses sur `/families/{id}/expenses` (US-DEP-2,
 * Lot 10 §3.5). **Best-effort, après commit** : un hub injoignable est journalisé, jamais remonté —
 * une mutation déjà écrite ne peut pas échouer parce que la diffusion a échoué (§4.7).
 *
 * La charge utile est un Turbo Stream `refresh` (Turbo 8) : le serveur pousse un **signal**, pas un
 * état. La liste, le solde et les agrégats sont relus depuis la source canonique, ce qui évite qu'un
 * montant transite en clair vers un abonné et qu'un écran affiche un solde calculé ailleurs.
 */
final class ExpensePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publish(Family $family): void
    {
        try {
            $topic = \sprintf('/families/%s/expenses', $family->getId());
            $this->hub->publish(new Update($topic, '<turbo-stream action="refresh"></turbo-stream>', private: true));
        } catch (\Throwable $e) {
            $this->logger->warning('Échec de publication temps réel des dépenses.', ['exception' => $e]);
        }
    }
}
