<?php

declare(strict_types=1);

namespace App\Security\Auth;

use App\Entity\AuditLog;
use App\Entity\Family;
use App\Entity\User;
use App\Enum\AuditAction;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Écrit une entrée AuditLog (append-only, cdc §5.4). À appeler en **dernier** dans un flux :
 * le flush persiste l'entrée d'audit avec les autres changements du même flux.
 */
final class AuditLogger
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        AuditAction $action,
        ?User $actor = null,
        ?Family $family = null,
        ?string $ip = null,
        array $metadata = [],
        ?string $entityType = null,
        ?string $entityId = null,
    ): void {
        $this->em->persist(new AuditLog($action, $actor, $family, $ip, $metadata, $entityType, $entityId));
        $this->em->flush();
    }
}
