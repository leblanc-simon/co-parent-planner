<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Custody;

use App\Service\Custody\Holidays\OpendatasoftSchoolHolidaysProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Adaptateur vacances via double HTTP (DoD §8.1) : mapping des champs réels, filtrage population,
 * filtrage académie, conversion UTC→Europe/Paris (sinon décalage d'un jour).
 */
final class OpendatasoftSchoolHolidaysProviderTest extends TestCase
{
    public function testMapsFiltersAndConvertsTimezone(): void
    {
        $payload = [
            'total_count' => 3,
            'results' => [
                [
                    'description' => 'Vacances de la Toussaint',
                    'population' => 'Élèves',
                    // 22:00 UTC = minuit heure de Paris (début du jour local suivant) → 2025-10-18.
                    'start_date' => '2025-10-17T22:00:00+00:00',
                    'end_date' => '2025-11-02T23:00:00+00:00',
                    'location' => 'Bordeaux',
                    'zones' => 'Zone A',
                    'annee_scolaire' => '2025-2026',
                ],
                [
                    'description' => 'Réunion enseignants',
                    'population' => 'Enseignants', // ignoré
                    'start_date' => '2025-10-17T22:00:00+00:00',
                    'end_date' => '2025-10-18T22:00:00+00:00',
                    'location' => 'Bordeaux',
                    'zones' => 'Zone A',
                    'annee_scolaire' => '2025-2026',
                ],
                [
                    'description' => 'Autre académie',
                    'population' => 'Élèves',
                    'start_date' => '2025-10-17T22:00:00+00:00',
                    'end_date' => '2025-11-02T23:00:00+00:00',
                    'location' => 'Paris', // académie différente → ignoré
                    'zones' => 'Zone A',
                    'annee_scolaire' => '2025-2026',
                ],
            ],
        ];

        $http = new MockHttpClient([new MockResponse(json_encode($payload, \JSON_THROW_ON_ERROR))]);
        $provider = new OpendatasoftSchoolHolidaysProvider($http, new ArrayAdapter(), new NullLogger());

        $result = $provider->fetch('A', 'Bordeaux', '2025-2026');

        self::assertCount(1, $result, 'population Enseignants et académie différente filtrées');
        self::assertSame('Vacances de la Toussaint', $result[0]->label);
        self::assertSame('2025-10-18', $result[0]->startDate->format('Y-m-d'), 'UTC 22:00 → date Europe/Paris J+1');
        // end_date = minuit Paris du 2025-11-03 = jour de RENTRÉE (borne exclusive) → dernier jour de
        // vacances = la veille, 2025-11-02 (sinon +1 jour d'école attribué en garde à chaque période).
        self::assertSame('2025-11-02', $result[0]->endDate->format('Y-m-d'), 'end_date rentrée exclusif → veille');
        self::assertSame('A', $result[0]->zone);
        self::assertSame('Bordeaux', $result[0]->academy);
        self::assertSame('2025-2026', $result[0]->schoolYear);
    }

    public function testThrowsOnHttpFailure(): void
    {
        $http = new MockHttpClient([new MockResponse('', ['http_code' => 503])]);
        $provider = new OpendatasoftSchoolHolidaysProvider($http, new ArrayAdapter(), new NullLogger());

        $this->expectException(\RuntimeException::class);
        $provider->fetch('A', 'Bordeaux', '2025-2026');
    }
}
