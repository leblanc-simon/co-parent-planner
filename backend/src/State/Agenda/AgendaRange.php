<?php

declare(strict_types=1);

namespace App\State\Agenda;

use App\Exception\AgendaInputException;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lit/valide la fenêtre [from, to] des requêtes d'agenda (US-AG-3, Lot 4 §4.2). Les paramètres
 * `?from`/`?to` sont des dates locales (Y-m-d) ; la fenêtre est normalisée aux bornes de journée du
 * fuseau famille puis convertie en instants UTC. Défaut : mois courant. `from > to` → 422 ; fenêtre
 * au-delà de `MAX_WINDOW_DAYS` → 422 (protection anti-expansion).
 */
final class AgendaRange
{
    /** Fenêtre maximale autorisée en lecture (§4.2). */
    public const int MAX_WINDOW_DAYS = 420;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} bornes UTC (from = 00:00 local, to = 23:59:59 local)
     */
    public function resolve(\DateTimeZone $tz): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $today = $this->clock->now()->setTimezone($tz);

        $fromDate = $this->parseDate($request?->query->get('from'), $tz) ?? $today->modify('first day of this month');
        $toDate = $this->parseDate($request?->query->get('to'), $tz) ?? $today->modify('last day of this month');

        if ($fromDate > $toDate) {
            throw new AgendaInputException('agenda.error.range_invalid');
        }
        if ((int) $fromDate->diff($toDate)->days > self::MAX_WINDOW_DAYS) {
            throw new AgendaInputException('agenda.error.window_too_large');
        }

        $utc = new \DateTimeZone('UTC');

        return [
            $fromDate->setTime(0, 0, 0)->setTimezone($utc),
            $toDate->setTime(23, 59, 59)->setTimezone($utc),
        ];
    }

    private function parseDate(mixed $raw, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $tz);
        if (false === $date || $date->format('Y-m-d') !== $raw) {
            throw new AgendaInputException('agenda.error.range_invalid');
        }

        return $date;
    }
}
