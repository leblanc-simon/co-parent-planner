<?php

declare(strict_types=1);

namespace App\Rgpd\Export;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Stockage local des exports (dev / bare-metal). Répertoire hors web-root (`%kernel.project_dir%/var/exports`
 * par défaut, surchargeable par `RGPD_EXPORT_DIR`). En prod, remplacer par une impl objet UE.
 *
 * Les clés sont contraintes (basename) pour interdire toute traversée de chemin.
 */
final class LocalExportStorage implements ExportStorage
{
    private readonly Filesystem $fs;

    public function __construct(
        #[Autowire('%env(resolve:RGPD_EXPORT_DIR)%')]
        private readonly string $directory,
    ) {
        $this->fs = new Filesystem();
    }

    public function store(string $key, string $contents): void
    {
        $this->fs->mkdir($this->directory);
        $this->fs->dumpFile($this->path($key), $contents);
    }

    public function read(string $key): string
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            throw new \RuntimeException(\sprintf('Artefact d\'export introuvable : %s', $key));
        }
        $contents = file_get_contents($path);
        if (false === $contents) {
            throw new \RuntimeException(\sprintf('Lecture de l\'artefact d\'export impossible : %s', $key));
        }

        return $contents;
    }

    public function exists(string $key): bool
    {
        return is_file($this->path($key));
    }

    public function delete(string $key): void
    {
        $this->fs->remove($this->path($key));
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, '/').'/'.basename($key);
    }
}
