<?php

declare(strict_types=1);

namespace App\Exception;

/** Une famille ne peut être liée à elle-même (§3.6). */
final class SelfLinkForbiddenException extends AbstractProblemException
{
    public const string TYPE = '/errors/self-link-forbidden';

    public function __construct()
    {
        parent::__construct(422, self::TYPE, 'familylink.error.self_link');
    }
}
