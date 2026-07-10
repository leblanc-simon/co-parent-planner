<?php

declare(strict_types=1);

namespace App\Dto\Rgpd;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\Rgpd\RegisterProvider;

/**
 * Une entrée du registre des traitements (US-RGPD-1), synthétisée depuis les attributs `#[PersonalData]`.
 * `GET /admin/rgpd/register` (admin technique + step-up).
 */
#[ApiResource(
    shortName: 'RgpdRegisterEntry',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/rgpd/register',
            paginationEnabled: false,
            provider: RegisterProvider::class,
        ),
    ],
)]
class RegisterEntry
{
    public string $entity = '';
    public string $field = '';
    public string $category = '';
    public bool $encrypted = false;
    public string $legalBasis = '';
    public string $purpose = '';
    public string $retention = '';
    public string $recipients = '';
}
