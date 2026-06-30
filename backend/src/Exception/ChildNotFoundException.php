<?php

declare(strict_types=1);

namespace App\Exception;

/** Enfant introuvable dans la famille active (résolution stricte, non révélateur — §4.4). */
final class ChildNotFoundException extends AbstractProblemException
{
    public const string TYPE = '/errors/child-not-found';

    public function __construct()
    {
        parent::__construct(404, self::TYPE, 'child.error.not_found');
    }
}
