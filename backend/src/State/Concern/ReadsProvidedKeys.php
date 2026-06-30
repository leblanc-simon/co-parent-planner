<?php

declare(strict_types=1);

namespace App\State\Concern;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lit l'ensemble des clés effectivement présentes dans le corps JSON de la requête courante
 * (sémantique PATCH partielle : « champ absent » ≠ « champ à null »).
 */
trait ReadsProvidedKeys
{
    abstract protected function requestStack(): RequestStack;

    /**
     * @return array<int, string>
     */
    protected function providedKeys(): array
    {
        $content = $this->requestStack()->getCurrentRequest()?->getContent() ?: '{}';
        $decoded = json_decode((string) $content, true);

        return \is_array($decoded) ? array_map(strval(...), array_keys($decoded)) : [];
    }
}
