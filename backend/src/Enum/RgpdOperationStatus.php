<?php

declare(strict_types=1);

namespace App\Enum;

enum RgpdOperationStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
}
