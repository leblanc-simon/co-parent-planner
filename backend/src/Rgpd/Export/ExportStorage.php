<?php

declare(strict_types=1);

namespace App\Rgpd\Export;

/**
 * Stockage des artefacts d'export RGPD (cdc §7 : stockage objet UE en prod). Abstrait derrière une
 * interface : l'impl locale ({@see LocalExportStorage}) sert le dev/bare-metal ; la prod branche une
 * impl S3-compatible UE sans changement du reste du code. Les clés jamais en base ; artefacts à TTL
 * court, purgés par la purge planifiée (scope `expired_exports`).
 */
interface ExportStorage
{
    /** Persiste l'artefact binaire sous la clé donnée (écrase si déjà présent). */
    public function store(string $key, string $contents): void;

    /** @throws \RuntimeException si l'artefact n'existe pas */
    public function read(string $key): string;

    public function exists(string $key): bool;

    /** Supprime l'artefact (no-op s'il n'existe pas). */
    public function delete(string $key): void;
}
