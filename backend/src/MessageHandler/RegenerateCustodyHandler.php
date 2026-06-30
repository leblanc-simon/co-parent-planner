<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Family;
use App\Message\RegenerateCustody;
use App\Service\Custody\CustodyGenerator;
use App\Service\Custody\CustodyPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/** Régénère l'horizon de garde d'une famille (idempotent). */
#[AsMessageHandler]
final class RegenerateCustodyHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CustodyGenerator $generator,
        private readonly CustodyPublisher $publisher,
    ) {
    }

    public function __invoke(RegenerateCustody $message): void
    {
        if (!Uuid::isValid($message->familyId)) {
            return;
        }
        $family = $this->em->getRepository(Family::class)->find(Uuid::fromString($message->familyId));
        if ($family instanceof Family && $family->getType()->hasCustody()) {
            $this->generator->generate($family);
            $this->publisher->publish($family);
        }
    }
}
