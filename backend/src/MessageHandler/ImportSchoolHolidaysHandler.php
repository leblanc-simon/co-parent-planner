<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Family;
use App\Message\ImportSchoolHolidays;
use App\Message\RegenerateCustody;
use App\Service\Custody\Holidays\SchoolHolidayImporter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Importe les vacances officielles puis déclenche une régénération de garde (les nouvelles périodes
 * n'ont pas encore de règle, mais l'horizon est rafraîchi). Idempotent ; retries gérés par Messenger.
 */
#[AsMessageHandler]
final class ImportSchoolHolidaysHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SchoolHolidayImporter $importer,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ImportSchoolHolidays $message): void
    {
        if (!Uuid::isValid($message->familyId)) {
            return;
        }
        $family = $this->em->getRepository(Family::class)->find(Uuid::fromString($message->familyId));
        if (!$family instanceof Family || !$family->getType()->hasCustody()) {
            return;
        }

        $count = $this->importer->import($family, $message->schoolYear);
        $this->logger->info('Import vacances terminé.', ['family' => $message->familyId, 'count' => $count]);

        $this->bus->dispatch(new RegenerateCustody($message->familyId));
    }
}
