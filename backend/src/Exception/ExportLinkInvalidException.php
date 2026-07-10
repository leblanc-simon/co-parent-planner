<?php

declare(strict_types=1);

namespace App\Exception;

/** Lien d'export invalide, altéré ou expiré (§2.3, §4.8) — refus uniforme 403. */
final class ExportLinkInvalidException extends AbstractProblemException
{
    public const string TYPE = '/errors/export-link-invalid';

    public function __construct()
    {
        parent::__construct(403, self::TYPE, 'rgpd.error.export_link_invalid');
    }
}
