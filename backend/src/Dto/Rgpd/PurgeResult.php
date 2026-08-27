<?php

declare(strict_types=1);

namespace App\Dto\Rgpd;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\State\Rgpd\PurgeProcessor;

/**
 * Résultat d'une purge RGPD (US-RGPD-3). `POST /admin/rgpd/purge?dryRun=true|false` (admin technique
 * + step-up) — **dry-run par défaut** : renvoie ce qui SERAIT purgé sans rien modifier.
 */
#[ApiResource(
    shortName: 'RgpdPurge',
    operations: [
        new Post(
            uriTemplate: '/admin/rgpd/purge',
            status: 200,
            read: false,
            deserialize: false,
            parameters: [
                'dryRun' => new QueryParameter(
                    description: "Simulation : `true` (défaut) n'écrit rien et renvoie le rapport ; `false` exécute réellement la purge.",
                    schema: ['type' => 'boolean', 'default' => true],
                ),
            ],
            processor: PurgeProcessor::class,
        ),
    ],
)]
class PurgeResult
{
    public string $mode = '';
    /** @var array<string, int> */
    public array $totals = [];
    /** @var list<array<string, string|int>> */
    public array $rules = [];

    /**
     * @param array<string, int>              $totals
     * @param list<array<string, string|int>> $rules
     */
    public function __construct(string $mode = '', array $totals = [], array $rules = [])
    {
        $this->mode = $mode;
        $this->totals = $totals;
        $this->rules = $rules;
    }
}
