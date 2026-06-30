<?php

declare(strict_types=1);

namespace App\State\Custody;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Custody\CreateExceptionRequest;
use App\Dto\Custody\CustodyExceptionResource;
use App\Enum\AuditAction;
use App\Security\Auth\AuditLogger;
use App\Security\Custody\CustodyAccess;
use App\Service\Custody\CustodyGenerator;
use App\Service\Custody\ExceptionWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-CUST-4 — crée une exception (custody:edit) puis matérialise ses jours en `source=exception`
 * (priorité maximale). La génération d'exception est synchrone et ciblée (plage courte).
 *
 * @implements ProcessorInterface<CreateExceptionRequest, CustodyExceptionResource>
 */
final class CreateExceptionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CustodyAccess $access,
        private readonly ExceptionWriter $writer,
        private readonly CustodyGenerator $generator,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustodyExceptionResource
    {
        \assert($data instanceof CreateExceptionRequest);

        $ctx = $this->access->write();
        $exception = $this->writer->build($ctx->family, $data, $ctx->membership);
        $this->em->persist($exception);

        $this->audit->log(
            AuditAction::CustodyExceptionCreated,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'CustodyException',
            entityId: (string) $exception->getId(),
        ); // flush

        $this->generator->applyException($exception);

        return CustodyExceptionResource::fromException($exception);
    }
}
