<?php

declare(strict_types=1);

namespace App\State\Custody;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Custody\CreateExceptionRequest;
use App\Dto\Custody\CustodyExceptionResource;
use App\Entity\CustodyException;
use App\Enum\AuditAction;
use App\Enum\NotificationType;
use App\Repository\CustodyExceptionRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Custody\CustodyAccess;
use App\Service\Custody\CustodyGenerator;
use App\Service\Custody\CustodyPublisher;
use App\Service\Custody\ExceptionWriter;
use App\Service\Notification\NotificationEmitter;
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
        private readonly CustodyExceptionRepository $exceptions,
        private readonly ExceptionWriter $writer,
        private readonly CustodyGenerator $generator,
        private readonly CustodyPublisher $publisher,
        private readonly NotificationEmitter $notifier,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustodyExceptionResource
    {
        \assert($data instanceof CreateExceptionRequest);

        $ctx = $this->access->write();
        $exception = $this->writer->build($ctx->family, $data, $ctx->membership);

        // Une exception portant DÉJÀ exactement cette plage est remplacée, jamais doublée :
        // « Échanger ce jour » deux fois de suite empilait deux exceptions contradictoires sur la
        // même date, que seul l'ordre d'application du moteur départageait, et que l'écran
        // Réglages listait toutes les deux. La plus ancienne s'en va donc ici.
        $superseded = $this->exceptions->findByExactRange($ctx->family, $exception->getStartDate(), $exception->getEndDate());
        foreach ($superseded as $previous) {
            // Mêmes dates, mêmes heures ET même parent : l'attribution ne change pas (rejeu réseau,
            // double clic, ou motif corrigé depuis les Réglages). On tient l'existante à jour
            // plutôt que d'en fabriquer une jumelle — l'opération est déclarée idempotente côté
            // clients (Idempotency-Key), le serveur tient la promesse. Des heures différentes, en
            // revanche, sont une autre intention : l'ancienne est remplacée, pas conservée.
            if ($this->sameParent($previous, $exception) && $this->sameTimes($previous, $exception)) {
                if ($previous->getReason() !== $exception->getReason()) {
                    $previous->setReason($exception->getReason());
                    $this->em->flush();
                }

                return CustodyExceptionResource::fromException($previous);
            }
        }
        foreach ($superseded as $previous) {
            $supersededId = (string) $previous->getId();
            $this->em->remove($previous);
            $this->audit->log(
                AuditAction::CustodyExceptionDeleted,
                $ctx->user(),
                $ctx->family,
                ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
                entityType: 'CustodyException',
                entityId: $supersededId,
            ); // flush (la suppression est committée avant la création qui la remplace)
        }

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
        $this->publisher->publish($ctx->family);
        // Notification e-mail (Lot 7) : changement de garde → membres habilités custody (hors auteur).
        //
        // Le corps NOMME le foyer désormais attributaire (E3) : « Le 12 septembre 2026 est chez
        // Sophie et Bob » vaut mieux que « le planning a été modifié », qui oblige à ouvrir
        // l'application pour savoir ce qui a changé. Ne sont passés ici que des IDENTIFIANTS : le
        // nom, chiffré au repos (`full_name` 🔒), est résolu à l'envoi — un payload Messenger vit en
        // clair dans `messenger_messages`, et l'y recopier ferait fuiter l'identité qu'on chiffre
        // partout ailleurs.
        $refs = [];
        $parent = $exception->getParentMembership();
        if (null !== $parent && $exception->getStartDate() == $exception->getEndDate()) {
            // Un seul jour : la phrase est exacte. Sur une période, la formulation « le {date} »
            // serait fausse, et le message générique reste juste.
            $refs = [
                'date' => $exception->getStartDate()->format('Y-m-d'),
                'membershipId' => (string) $parent->getId(),
            ];
        }
        $this->notifier->emit(NotificationType::CustodyChanged, $ctx->family, $ctx->membership, (string) $exception->getId(), $refs);

        return CustodyExceptionResource::fromException($exception);
    }

    private function sameTimes(CustodyException $a, CustodyException $b): bool
    {
        return $a->getStartTime() === $b->getStartTime() && $a->getEndTime() === $b->getEndTime();
    }

    private function sameParent(CustodyException $a, CustodyException $b): bool
    {
        $left = $a->getParentMembership()?->getId();
        $right = $b->getParentMembership()?->getId();

        return null !== $left && null !== $right && $left->equals($right);
    }
}
