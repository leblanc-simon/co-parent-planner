<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Custody;

use App\Enum\CustodyDaySource;
use App\Service\Custody\DaySegment;
use App\Service\Custody\DaySegments;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Découpage horaire d'une journée (cdc §3.4). Deux règles s'y croisent :
 *
 *  - le **nominal** suit la convention de transmission — la journée appartient au parent du jour
 *    jusqu'à l'heure d'échange, puis à celui du lendemain ;
 *  - les **exceptions** s'appliquent à la lettre, sur leurs propres heures, par-dessus.
 *
 * Les deux exemples de la demande du 2026-09-11 y sont rejoués tels quels.
 */
#[CoversClass(DaySegments::class)]
#[CoversClass(DaySegment::class)]
final class DaySegmentsTest extends TestCase
{
    private const string A = 'aaaaaaaa-0000-4000-8000-000000000001';
    private const string B = 'bbbbbbbb-0000-4000-8000-000000000002';

    public function testNominalDaySplitsAtHandoverTimeWhenTheNextDayChangesParent(): void
    {
        $segments = DaySegments::nominal(self::A, self::B, 18 * 60, CustodyDaySource::Base, CustodyDaySource::Base);

        self::assertSame(
            [
                ['from' => '00:00', 'to' => '18:00', 'parent' => self::A, 'source' => 'base'],
                ['from' => '18:00', 'to' => '24:00', 'parent' => self::B, 'source' => 'base'],
            ],
            $segments->toArray(),
        );
        // 18 h sur 24 : la journée est comptée au parent du jour.
        self::assertSame(self::A, $segments->majorityParentId());
        self::assertFalse($segments->isUniform());
    }

    public function testNominalDayStaysWholeWithoutAnythingToShare(): void
    {
        // Même parent le lendemain.
        self::assertTrue(DaySegments::nominal(self::A, self::A, 18 * 60, CustodyDaySource::Base, CustodyDaySource::Base)->isUniform());
        // Lendemain hors horizon généré : on ne devine pas.
        self::assertTrue(DaySegments::nominal(self::A, null, 18 * 60, CustodyDaySource::Base, CustodyDaySource::Base)->isUniform());
        // Pas d'heure d'échange, ou échange à minuit : la journée entière revient au parent du jour.
        self::assertTrue(DaySegments::nominal(self::A, self::B, null, CustodyDaySource::Base, CustodyDaySource::Base)->isUniform());
        self::assertTrue(DaySegments::nominal(self::A, self::B, 0, CustodyDaySource::Base, CustodyDaySource::Base)->isUniform());
    }

    /**
     * Demande du 2026-09-11, premier exemple. « A -> 25 | B -> 26, 27, 28 », échange 18:00 : le 26
     * est uni chez B (le 27 est à B aussi). L'échange du 26 vers A part de la transmission de la
     * veille et court jusqu'à celle du jour même — le 26 se peint donc A jusqu'à 18:00, B ensuite.
     */
    public function testSwapTakesTheDayUntilTheHandover(): void
    {
        $day26 = DaySegments::nominal(self::B, self::B, 18 * 60, CustodyDaySource::Base, CustodyDaySource::Base)
            ->overlay(0, 18 * 60, self::A, CustodyDaySource::Exception);

        self::assertSame(
            [
                ['from' => '00:00', 'to' => '18:00', 'parent' => self::A, 'source' => 'exception'],
                ['from' => '18:00', 'to' => '24:00', 'parent' => self::B, 'source' => 'base'],
            ],
            $day26->toArray(),
        );
        self::assertSame(self::A, $day26->majorityParentId());
        self::assertSame(CustodyDaySource::Exception, $day26->dominantSource());

        // La veille (25) est prolongée jusqu'à minuit : elle redevient une journée unie chez A.
        $day25 = DaySegments::nominal(self::A, self::B, 18 * 60, CustodyDaySource::Base, CustodyDaySource::Base)
            ->overlay(18 * 60, DaySegment::MINUTES_PER_DAY, self::A, CustodyDaySource::Exception);
        self::assertTrue($day25->isUniform());
        self::assertSame(self::A, $day25->majorityParentId());
    }

    /**
     * Demande du 2026-09-11, second exemple. « A -> 25, 29 | B -> 26, 27, 28 » : le 28 est le
     * dernier jour de B, donc déjà partagé (B jusqu'à 18:00, A ensuite). L'échange du 28 vers A,
     * qui ne peut pas s'adosser à la veille (elle est à B), commence le matin même : la journée
     * devient « B jusqu'à 8:00, A ensuite » — deux tranches, la seconde fusionnée avec le rythme.
     */
    public function testPartialSwapMergesWithTheRhythmThatFollows(): void
    {
        $day28 = DaySegments::nominal(self::B, self::A, 18 * 60, CustodyDaySource::Base, CustodyDaySource::Base)
            ->overlay(8 * 60, 18 * 60, self::A, CustodyDaySource::Exception);

        // Deux tranches, pas trois : l'échange se prolonge dans le rythme du même parent, et une
        // frontière à 18:00 dessinerait un passage qui n'a pas lieu.
        self::assertSame(
            [
                ['from' => '00:00', 'to' => '08:00', 'parent' => self::B, 'source' => 'base'],
                ['from' => '08:00', 'to' => '24:00', 'parent' => self::A, 'source' => 'exception'],
            ],
            $day28->toArray(),
        );
        // 16 h sur 24 chez A : la journée lui est comptée, et la case porte son initiale.
        self::assertSame(self::A, $day28->majorityParentId());
    }

    /** Un échange encadré par le rythme des deux côtés découpe la journée en TROIS tranches. */
    public function testSwapInsideTheDayProducesThreeSegments(): void
    {
        $segments = DaySegments::nominal(self::A, self::A, 18 * 60, CustodyDaySource::Base, CustodyDaySource::Base)
            ->overlay(10 * 60, 12 * 60, self::B, CustodyDaySource::Exception);

        self::assertSame(['00:00', '10:00', '12:00'], array_column($segments->toArray(), 'from'));
        self::assertSame(['10:00', '12:00', '24:00'], array_column($segments->toArray(), 'to'));
        self::assertSame(self::A, $segments->majorityParentId());
    }

    /** À égalité parfaite, la journée revient à qui couche les enfants. */
    public function testTiedDayGoesToWhoeverHasTheNight(): void
    {
        $segments = DaySegments::nominal(self::A, self::B, 12 * 60, CustodyDaySource::Base, CustodyDaySource::Base);

        self::assertSame(self::B, $segments->majorityParentId());
    }

    public function testStoredFormRoundTripsAndFallsBackOnGarbage(): void
    {
        $segments = DaySegments::nominal(self::A, self::B, 18 * 60, CustodyDaySource::Base, CustodyDaySource::Holiday);
        self::assertSame($segments->toArray(), DaySegments::fromArray($segments->toArray(), null, CustodyDaySource::Base)->toArray());

        // Lignes écrites avant que la garde ne connaisse les heures : journée unie chez son parent.
        $legacy = DaySegments::fromArray(null, self::A, CustodyDaySource::Holiday);
        self::assertTrue($legacy->isUniform());
        self::assertSame(self::A, $legacy->majorityParentId());
        self::assertSame(CustodyDaySource::Holiday, $legacy->dominantSource());

        // Contenu illisible : on retombe sur la journée entière plutôt que de peindre au hasard.
        self::assertTrue(DaySegments::fromArray([['from' => 'midi', 'to' => '24:00']], self::A, CustodyDaySource::Base)->isUniform());
    }
}
