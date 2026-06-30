<?php

declare(strict_types=1);

namespace App\Exception;

/** Invariant is_admin ⇒ role=parent (§3.7) : admin demandé sur un rôle non-parent → rejet. */
final class AdminRequiresParentRoleException extends AbstractProblemException
{
    public const string TYPE = '/errors/admin-requires-parent-role';

    public function __construct()
    {
        parent::__construct(422, self::TYPE, 'membership.error.admin_requires_parent');
    }
}
