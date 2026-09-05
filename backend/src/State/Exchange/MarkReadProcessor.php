<?php

declare(strict_types=1);

namespace App\State\Exchange;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Exchange\ExchangeResource;
use App\Repository\ExchangeReadRepository;
use App\Repository\MessageRepository;
use App\Security\Exchanges\ExchangeAccess;
use App\Service\Exchange\ExchangeAssembler;
use Doctrine\ORM\EntityManagerInterface;

/**
 * X5 — marque un échange **lu par l'appelant**.
 *
 * `exchanges:view` suffit : lire n'est pas écrire dans l'échange, et un membre en lecture seule doit
 * pouvoir faire tomber son propre marqueur. Rien n'est publié sur Mercure — la lecture est
 * strictement personnelle, personne d'autre n'a à savoir quand j'ouvre un fil.
 *
 * Verbe **POST**, jamais un effet de bord du `GET` du détail : le projet a déjà tranché ainsi pour
 * l'aperçu d'invitation (« GET = aperçu non consommant »), et un GET qui écrit se déclenche au
 * survol dès qu'un client préfetche.
 *
 * @implements ProcessorInterface<mixed, ExchangeResource>
 */
final class MarkReadProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExchangeAccess $access,
        private readonly ExchangeReadRepository $reads,
        private readonly MessageRepository $messages,
        private readonly ExchangeAssembler $assembler,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ExchangeResource
    {
        $ctx = $this->access->read();
        $tz = new \DateTimeZone($ctx->family->getTimezone());
        $exchange = $this->access->loadInFamily($uriVariables['id'] ?? null, $ctx->family);

        // Le repère est le dernier message, pas l'heure : cf. `ExchangeAssembler::isUnread()`.
        $this->reads->markRead(
            $ctx->membership,
            $exchange,
            new \DateTimeImmutable('now'),
            $this->messages->lastFor($exchange),
        );
        $this->em->flush();

        return $this->assembler->detail($exchange, $tz, $ctx->membership);
    }
}
