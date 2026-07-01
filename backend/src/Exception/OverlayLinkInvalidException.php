<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Overlay demandé sur une famille non liée (ou lien non `active`, ou soi-même) — Lot 5 §3.2/§4.4.
 * Réponse **uniforme** (ne révèle pas l'existence de la famille cible, non-énumération §4.8) → 422.
 */
final class OverlayLinkInvalidException extends AbstractProblemException
{
    public const string TYPE = '/errors/overlay-link-invalid';

    public function __construct()
    {
        parent::__construct(422, self::TYPE, 'overlay.error.link_invalid');
    }
}
