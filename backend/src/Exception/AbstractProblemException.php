<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Base des exceptions métier RFC 7807 : statut + URI de type + clé de traduction du detail.
 */
abstract class AbstractProblemException extends HttpException implements ProblemException
{
    /**
     * @param array<string, string|int> $detailParams
     * @param array<string, string>     $headers
     */
    public function __construct(
        int $statusCode,
        private readonly string $problemType,
        private readonly string $detailKey,
        private readonly array $detailParams = [],
        array $headers = [],
    ) {
        parent::__construct($statusCode, $detailKey, headers: $headers);
    }

    public function problemType(): string
    {
        return $this->problemType;
    }

    public function detailKey(): string
    {
        return $this->detailKey;
    }

    public function detailParams(): array
    {
        return $this->detailParams;
    }
}
