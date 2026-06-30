<?php

declare(strict_types=1);

namespace App\State\Custody;

use App\Exception\CustodyInputException;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lit/valide la fenêtre [from, to] des requêtes de garde (US-CUST-5). Défaut : mois courant
 * (Europe/Paris). `from > to` → 422 ; dates non parsables → 422.
 */
final class CustodyRange
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} */
    public function resolve(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $today = $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'));

        $fromRaw = $request?->query->get('from');
        $toRaw = $request?->query->get('to');

        $from = $this->parse($fromRaw) ?? $today->modify('first day of this month');
        $to = $this->parse($toRaw) ?? $today->modify('last day of this month');

        if ($from > $to) {
            throw new CustodyInputException('custody.error.range_invalid');
        }

        return [$from, $to];
    }

    private function parse(mixed $raw): ?\DateTimeImmutable
    {
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if (false === $d || $d->format('Y-m-d') !== $raw) {
            throw new CustodyInputException('custody.error.range_invalid');
        }

        return $d;
    }
}
