<?php

declare(strict_types=1);

namespace App\Dto\Rgpd;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\State\Rgpd\ExportProcessor;
use App\State\Rgpd\ExportStatusProvider;

/**
 * Export RGPD du compte courant (US-SET-4). `POST /me/export` crée une opération asynchrone (202) ;
 * `GET /me/export/{id}` renvoie son statut et, une fois `done`, un **lien signé temporaire** de
 * téléchargement (§3.5). Le téléchargement lui-même passe par {@see \App\Controller\Rgpd\ExportDownloadController}.
 */
#[ApiResource(
    shortName: 'MeExport',
    operations: [
        new Post(
            uriTemplate: '/me/export',
            status: 202,
            read: false,
            deserialize: false,
            processor: ExportProcessor::class,
        ),
        new Get(
            uriTemplate: '/me/export/{id}',
            provider: ExportStatusProvider::class,
        ),
    ],
)]
class ExportResource
{
    public string $id = '';
    public string $status = '';
    public ?string $downloadUrl = null;

    public function __construct(string $id = '', string $status = '', ?string $downloadUrl = null)
    {
        $this->id = $id;
        $this->status = $status;
        $this->downloadUrl = $downloadUrl;
    }
}
