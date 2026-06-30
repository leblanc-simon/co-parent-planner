<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Exception métier convertible en réponse RFC 7807 (`application/problem+json`) localisée.
 *
 * Implémentée par les exceptions du domaine ; le {@see \App\EventSubscriber\ProblemExceptionSubscriber}
 * lit le `type` et la **clé de traduction** du `detail` de façon générique (pas de match par classe).
 */
interface ProblemException extends HttpExceptionInterface
{
    /** URI stable identifiant la classe de problème (ex. `/errors/last-admin-protected`). */
    public function problemType(): string;

    /** Clé de traduction ICU du `detail` (résolue en `fr-FR`). */
    public function detailKey(): string;

    /**
     * Paramètres ICU passés à la traduction du `detail` (ex. `['domain' => 'family_settings']`).
     *
     * @return array<string, string|int>
     */
    public function detailParams(): array;
}
