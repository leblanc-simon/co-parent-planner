<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CustodyPattern;
use App\Message\ExtendCustodyHorizon;
use App\Message\ImportSchoolHolidays;
use App\Message\RefreshSchoolHolidays;
use App\Message\RegenerateCustody;
use App\Repository\CustodyPatternRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Tâches planifiées de garde (Lot 3 §3.9), idempotentes : fan-out vers une régénération / un import
 * par famille à rythme. Le travail réel est délégué aux handlers par famille (sérialisés par verrou).
 */
final class CustodyScheduleHandler
{
    public function __construct(
        private readonly CustodyPatternRepository $patterns,
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[AsMessageHandler]
    public function extendHorizon(ExtendCustodyHorizon $message): void
    {
        foreach ($this->patterns->findAll() as $pattern) {
            \assert($pattern instanceof CustodyPattern);
            $this->bus->dispatch(new RegenerateCustody((string) $pattern->getFamily()->getId()));
        }
    }

    #[AsMessageHandler]
    public function refreshHolidays(RefreshSchoolHolidays $message): void
    {
        foreach ($this->patterns->findAll() as $pattern) {
            \assert($pattern instanceof CustodyPattern);
            $family = $pattern->getFamily();
            if (null !== $family->getSchoolZone() && null !== $family->getAcademy()) {
                $this->bus->dispatch(new ImportSchoolHolidays((string) $family->getId()));
            }
        }
    }
}
