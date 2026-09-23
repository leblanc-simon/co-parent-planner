<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CustodyPattern;
use App\Message\RegenerateCustody;
use App\MessageHandler\RegenerateCustodyHandler;
use App\Repository\CustodyPatternRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Régénère **tout de suite** l'horizon de garde d'une famille, ou de toutes les familles à rythme,
 * sans passer par le worker.
 *
 * L'extension quotidienne (`ExtendCustodyHorizon`) finit par tout recalculer, mais une migration
 * qui change ce que le moteur matérialise (ex. `custody_days.segments`) ne doit pas attendre son
 * passage : c'est l'étape de mise à jour à lancer après `doctrine:migrations:migrate`
 * (`infra/INSTALL.md`). Idempotente, passé figé — le moteur ne réécrit que `date >= aujourd'hui`.
 */
#[AsCommand(name: 'app:custody:regenerate', description: 'Régénère l\'horizon de garde matérialisé (une famille ou toutes).')]
final class RegenerateCustodyCommand
{
    public function __construct(
        private readonly CustodyPatternRepository $patterns,
        private readonly RegenerateCustodyHandler $handler,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Identifiant de la famille ; omis = toutes les familles à rythme.')]
        ?string $family = null,
    ): int {
        if (null !== $family && !Uuid::isValid($family)) {
            $io->error(\sprintf('Identifiant de famille invalide : %s', $family));

            return Command::INVALID;
        }

        $ids = null !== $family
            ? [$family]
            : array_map(static fn (CustodyPattern $p): string => (string) $p->getFamily()->getId(), $this->patterns->findAll());

        foreach ($ids as $id) {
            ($this->handler)(new RegenerateCustody($id));
        }

        $io->success(\sprintf('%d famille(s) régénérée(s).', \count($ids)));

        return Command::SUCCESS;
    }
}
