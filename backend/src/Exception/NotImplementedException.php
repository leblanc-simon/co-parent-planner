<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Endpoint routé et présent dans l'OpenAPI mais non encore fonctionnel → 501 (décision D7 §0.5).
 * On n'utilise jamais 404 pour « pas encore prêt » (cohérence OpenAPI ↔ client généré).
 */
final class NotImplementedException extends HttpException
{
    public function __construct(string $message = 'Fonctionnalité non encore disponible (prévue au Lot 8).')
    {
        parent::__construct(501, $message);
    }
}
