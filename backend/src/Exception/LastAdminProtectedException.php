<?php

declare(strict_types=1);

namespace App\Exception;

/** Dernier admin protégé : ni retrait ni rétrogradation (invariant §3.2). */
final class LastAdminProtectedException extends AbstractProblemException
{
    public const string TYPE = '/errors/last-admin-protected';

    public function __construct()
    {
        parent::__construct(409, self::TYPE, 'family.error.last_admin_protected');
    }
}
