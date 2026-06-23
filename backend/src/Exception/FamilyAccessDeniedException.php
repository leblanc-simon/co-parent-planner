<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Famille inexistante OU utilisateur non membre actif — réponse indistincte (anti-énumération, §3.6).
 */
final class FamilyAccessDeniedException extends HttpException
{
    public const string TYPE = '/errors/family-forbidden';

    public function __construct(string $message = 'Famille non autorisée.')
    {
        parent::__construct(403, $message);
    }
}
