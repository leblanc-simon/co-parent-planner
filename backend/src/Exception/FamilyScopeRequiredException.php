<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Header X-Family-Id absent ou malformé sur une ressource scopée (§3.6, §6.5).
 */
final class FamilyScopeRequiredException extends HttpException
{
    public const string TYPE = '/errors/family-scope-required';

    public function __construct(string $message = 'Le header X-Family-Id est requis pour cette ressource.', int $statusCode = 400)
    {
        parent::__construct($statusCode, $message);
    }
}
