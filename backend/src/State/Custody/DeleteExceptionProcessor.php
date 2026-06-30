<?php

declare(strict_types=1);

namespace App\State\Custody;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\CustodyException;
use App\Enum\AuditAction;
use App\Exception\CustodyNotFoundException;
use App\Repository\CustodyExceptionRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Custody\CustodyAccess;
use App\Service\Custody\CustodyGenerator;
use App\Service\Custody\CustodyPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * US-CUST-4 — supprime une exception (custody:edit) puis recalcule sa plage (retour vacances/base).
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class DeleteExceptionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CustodyAccess $access,
        private readonly CustodyExceptionRepository $exceptions,
        private readonly CustodyGenerator $generator,
        private readonly CustodyPublisher $publisher,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $ctx = $this->access->write();
        $exception = $this->load($uriVariables['exceptionId'] ?? null, $ctx->family);

        $start = $exception->getStartDate();
        $end = $exception->getEndDate();
        $id = (string) $exception->getId();

        $this->em->remove($exception);
        $this->audit->log(
            AuditAction::CustodyExceptionDeleted,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'CustodyException',
            entityId: $id,
        ); // flush (la suppression est committée avant le recalcul)

        $this->generator->recomputeWindow($ctx->family, $start, $end);
        $this->publisher->publish($ctx->family);

        return null;
    }

    private function load(mixed $id, \App\Entity\Family $family): CustodyException
    {
        if (!\is_string($id) || !Uuid::isValid($id)) {
            throw new CustodyNotFoundException();
        }
        $exception = $this->exceptions->find(Uuid::fromString($id));
        if (!$exception instanceof CustodyException || !$exception->getFamily()->getId()->equals($family->getId())) {
            throw new CustodyNotFoundException();
        }

        return $exception;
    }
}
