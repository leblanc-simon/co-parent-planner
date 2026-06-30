<?php

declare(strict_types=1);

namespace App\Service\Note;

use App\Dto\Note\CreateNoteRequest;
use App\Dto\Note\UpdateNoteRequest;
use App\Entity\Child;
use App\Entity\Note;
use App\Exception\ChildInputException;
use App\Service\Child\NoteDateValidator;
use Psr\Clock\ClockInterface;

/**
 * Applique texte et date à une note (création/édition), avec défaut « aujourd'hui » (fuseau famille)
 * et revalidation de cohérence de `note_date` (§3.8) à chaque écriture.
 */
final class NoteWriter
{
    public function __construct(
        private readonly NoteDateValidator $dateValidator,
        private readonly ClockInterface $clock,
    ) {
    }

    public function applyCreate(Note $note, CreateNoteRequest $data, Child $child, string $timezone): void
    {
        \assert(null !== $data->text);
        $note->setText(trim($data->text));
        $date = $this->resolveDate($data->noteDate, $timezone);
        $this->dateValidator->validate($date, $child, $timezone);
        $note->setNoteDate($date);
    }

    /**
     * @param array<int, string> $provided
     */
    public function applyUpdate(Note $note, UpdateNoteRequest $data, array $provided, string $timezone): void
    {
        if (\in_array('text', $provided, true) && null !== $data->text) {
            $note->setText(trim($data->text));
        }
        if (\in_array('noteDate', $provided, true)) {
            $date = $this->resolveDate($data->noteDate, $timezone);
            $this->dateValidator->validate($date, $note->getChild(), $timezone);
            $note->setNoteDate($date);
        }
    }

    private function resolveDate(?string $raw, string $timezone): \DateTimeImmutable
    {
        $tz = new \DateTimeZone($timezone);
        if (null === $raw || '' === trim($raw)) {
            return \DateTimeImmutable::createFromFormat('!Y-m-d', $this->clock->now()->setTimezone($tz)->format('Y-m-d'), $tz)
                ?: throw new ChildInputException('note.error.date_future');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($raw), $tz);
        if (false === $date || $date->format('Y-m-d') !== trim($raw)) {
            throw new ChildInputException('note.error.date_invalid');
        }

        return $date;
    }
}
