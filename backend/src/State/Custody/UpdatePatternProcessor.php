<?php

declare(strict_types=1);

namespace App\State\Custody;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Custody\CustodyPatternResource;
use App\Dto\Custody\UpdatePatternRequest;
use App\Entity\CustodyPattern;
use App\Enum\AuditAction;
use App\Message\RegenerateCustody;
use App\Repository\CustodyPatternRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Custody\CustodyAccess;
use App\Service\Custody\PatternWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * US-CUST-1/2/9 — crée/modifie le rythme (custody:edit, famille séparée). Régénère le futur en
 * asynchrone (sans écraser exceptions/règles). Répond vite (200), génération via Messenger.
 *
 * @implements ProcessorInterface<UpdatePatternRequest, CustodyPatternResource>
 */
final class UpdatePatternProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CustodyAccess $access,
        private readonly CustodyPatternRepository $patterns,
        private readonly PatternWriter $writer,
        private readonly MessageBusInterface $bus,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustodyPatternResource
    {
        \assert($data instanceof UpdatePatternRequest);

        $ctx = $this->access->write();
        $family = $ctx->family;

        $pattern = $this->patterns->findOneByFamily($family) ?? (new CustodyPattern())->setFamily($family);
        $this->writer->apply($pattern, $family, $data);

        if (!$this->em->contains($pattern)) {
            $this->em->persist($pattern);
        }

        $this->audit->log(
            AuditAction::CustodyPatternUpdated,
            $ctx->user(),
            $family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            metadata: ['kind' => $pattern->getKind()->value],
            entityType: 'CustodyPattern',
            entityId: (string) $pattern->getId(),
        ); // flush

        // Régénération asynchrone du futur (idempotente).
        $this->bus->dispatch(new RegenerateCustody((string) $family->getId()));

        return CustodyPatternResource::fromPattern($pattern);
    }
}
