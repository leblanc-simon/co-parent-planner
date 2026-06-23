<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** Invitation invalide, expirée, révoquée ou déjà acceptée (US-ONB-1 sc.4). */
final class InvalidInvitationException extends HttpException
{
    public const string TYPE = '/errors/invalid-invitation';

    public function __construct()
    {
        parent::__construct(400, 'invitation.error.invalid');
    }
}
