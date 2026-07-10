<?php

declare(strict_types=1);

namespace App\Exception;

/** Opération RGPD introuvable ou non finalisée (non révélateur : 404 uniforme). */
final class RgpdOperationNotFoundException extends AbstractProblemException
{
    public const string TYPE = '/errors/rgpd-operation-not-found';

    public function __construct()
    {
        parent::__construct(404, self::TYPE, 'rgpd.error.operation_not_found');
    }
}
