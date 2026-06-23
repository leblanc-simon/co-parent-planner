<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** L'e-mail de l'utilisateur connecté ne correspond pas à l'invité (US-ONB-1 sc.5). */
final class InvitationEmailMismatchException extends HttpException
{
    public const string TYPE = '/errors/invitation-email-mismatch';

    public function __construct()
    {
        parent::__construct(403, 'invitation.error.email_mismatch');
    }
}
