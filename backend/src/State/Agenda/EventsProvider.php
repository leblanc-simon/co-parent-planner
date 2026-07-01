<?php

declare(strict_types=1);

namespace App\State\Agenda;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Agenda\EventResource;
use App\Enum\EventCategory;
use App\Exception\AgendaInputException;
use App\Security\Agenda\AgendaAccess;
use App\Service\Agenda\AgendaReader;
use App\Service\Agenda\Occurrence;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-AG-3 — occurrences d'agenda sur [from, to] (agenda:view), filtrables par `category` (CSV).
 * Le paramètre `overlay=` est réservé au Lot 5 (ignoré ici).
 *
 * @implements ProviderInterface<EventResource>
 */
final class EventsProvider implements ProviderInterface
{
    public function __construct(
        private readonly AgendaAccess $access,
        private readonly AgendaReader $reader,
        private readonly AgendaRange $range,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<EventResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $ctx = $this->access->read();
        $tz = new \DateTimeZone($ctx->family->getTimezone());
        [$from, $to] = $this->range->resolve($tz);
        $categories = $this->parseCategories();

        return array_map(
            static fn (Occurrence $o): EventResource => EventResource::fromOccurrence($o, $tz),
            $this->reader->occurrences($ctx->family, $from, $to, $categories),
        );
    }

    /**
     * @return list<EventCategory>|null
     */
    private function parseCategories(): ?array
    {
        $raw = $this->requestStack->getCurrentRequest()?->query->get('category');
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        $categories = [];
        foreach (explode(',', $raw) as $token) {
            $token = trim($token);
            if ('' === $token) {
                continue;
            }
            $category = EventCategory::tryFrom($token);
            if (null === $category) {
                throw new AgendaInputException('agenda.error.category_invalid');
            }
            $categories[] = $category;
        }

        return [] === $categories ? null : $categories;
    }
}
