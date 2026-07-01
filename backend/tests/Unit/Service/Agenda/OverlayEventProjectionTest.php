<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Agenda;

use App\Dto\Agenda\OverlayEventResource;
use App\Entity\Child;
use App\Entity\Membership;
use App\Enum\EventCategory;
use App\Service\Agenda\Occurrence;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Anti-fuite (Lot 5 §3.6) : la projection overlay ne doit exposer QUE l'horaire, la catégorie et le
 * titre — même quand l'occurrence source porte enfants, responsable, description, lieu et série.
 */
final class OverlayEventProjectionTest extends TestCase
{
    public function testProjectionExposesOnlyMinimalFields(): void
    {
        $tz = new \DateTimeZone('Europe/Paris');
        $occurrence = new Occurrence(
            eventId: Uuid::v7(),
            seriesId: Uuid::v7(),
            recurrenceDate: new \DateTimeImmutable('2026-06-15', $tz),
            startsAt: new \DateTimeImmutable('2026-06-15 15:30', new \DateTimeZone('UTC')),
            endsAt: new \DateTimeImmutable('2026-06-15 16:30', new \DateTimeZone('UTC')),
            allDay: false,
            category: EventCategory::Sport,
            title: 'Judo',
            location: 'Dojo privé',
            description: 'Note confidentielle du foyer lié',
            responsibleMembership: new Membership(),
            wholeFamily: false,
            children: [new Child(), new Child()],
            isRecurring: true,
            isOverride: false,
        );

        $dto = OverlayEventResource::fromOccurrence($occurrence, $tz, 'family-b-id');

        // Champs exposés attendus.
        self::assertSame('Judo', $dto->title);
        self::assertSame('sport', $dto->category);
        self::assertSame('linked', $dto->source);
        self::assertSame('#9C6FB0', $dto->color);
        self::assertFalse($dto->editable);
        self::assertSame('family-b-id', $dto->linkedFamilyId);
        self::assertStringStartsWith('2026-06-15T17:30:00', $dto->startsAt); // 15:30 UTC → 17:30 Paris

        // Aucun champ hors projection minimale (propriétés publiques du DTO).
        $exposed = array_keys(get_object_vars($dto));
        sort($exposed);
        self::assertSame(
            ['allDay', 'category', 'color', 'editable', 'endsAt', 'linkedFamilyId', 'source', 'startsAt', 'title'],
            $exposed,
        );
        foreach (['childIds', 'responsibleMembershipId', 'description', 'location', 'seriesId', 'id', 'recurrence'] as $forbidden) {
            self::assertNotContains($forbidden, $exposed, "champ interdit dans la projection : {$forbidden}");
        }
    }
}
