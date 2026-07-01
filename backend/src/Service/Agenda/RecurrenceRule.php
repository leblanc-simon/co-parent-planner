<?php

declare(strict_types=1);

namespace App\Service\Agenda;

use App\Enum\RecurrenceFreq;

/**
 * Règle de récurrence d'une série, portée par le maître (cdc §2.5 ; Lot 4 §3.1). Value object
 * immuable : `until` **et** `count` sont mutuellement exclusifs (§4.2) ; `until` est une date locale
 * **inclusive**. La validation sémantique (exclusivité, bornes) est faite par l'`EventWriter` ; ici
 * on ne fait que parser/sérialiser le champ `recurrence` (jsonb) stocké sur l'entité.
 */
final class RecurrenceRule
{
    public function __construct(
        public readonly RecurrenceFreq $freq,
        public readonly ?\DateTimeImmutable $until = null,
        public readonly ?int $count = null,
        public readonly bool $excludeHolidays = false,
    ) {
    }

    public static function none(): self
    {
        return new self(RecurrenceFreq::None);
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        if (null === $data) {
            return self::none();
        }

        $rawFreq = \is_string($data['freq'] ?? null) ? $data['freq'] : 'none';
        $freq = RecurrenceFreq::tryFrom($rawFreq) ?? RecurrenceFreq::None;
        if (!$freq->isRecurring()) {
            return self::none();
        }

        $until = null;
        if (isset($data['until']) && \is_string($data['until']) && '' !== $data['until']) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['until']);
            $until = false !== $parsed ? $parsed : null;
        }

        $count = isset($data['count']) && \is_int($data['count']) ? $data['count'] : null;
        $excludeHolidays = (bool) ($data['excludeHolidays'] ?? false);

        return new self($freq, $until, $count, $excludeHolidays);
    }

    /**
     * @return array{freq: string, until: string|null, count: int|null, excludeHolidays: bool}
     */
    public function toArray(): array
    {
        return [
            'freq' => $this->freq->value,
            'until' => $this->until?->format('Y-m-d'),
            'count' => $this->count,
            'excludeHolidays' => $this->excludeHolidays,
        ];
    }
}
