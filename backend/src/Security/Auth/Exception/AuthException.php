<?php

declare(strict_types=1);

namespace App\Security\Auth\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Base des erreurs d'authentification (mappées RFC 7807 via le subscriber d'exceptions).
 * Le `type` alimente le champ `type` du problème ; les messages sont des clés de traduction.
 */
abstract class AuthException extends HttpException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(int $statusCode, string $message, array $headers = [])
    {
        parent::__construct($statusCode, $message, headers: $headers);
    }

    /** Identifiant RFC 7807 (relatif). */
    abstract public function problemType(): string;
}
