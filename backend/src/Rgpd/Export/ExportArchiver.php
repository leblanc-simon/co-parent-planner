<?php

declare(strict_types=1);

namespace App\Rgpd\Export;

/**
 * Sérialise les données d'export en archive ZIP (`export.json` + place pour d'éventuelles pièces).
 * Format autonome, portable (droit à la portabilité, §3.5).
 */
final class ExportArchiver
{
    /**
     * @param array<string, mixed> $data
     */
    public function archive(array $data): string
    {
        $json = json_encode(
            $data,
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
        );

        $tmp = tempnam(sys_get_temp_dir(), 'rgpd_export_');
        if (false === $tmp) {
            throw new \RuntimeException('Impossible de créer un fichier temporaire d\'export.');
        }

        try {
            $zip = new \ZipArchive();
            if (true !== $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
                throw new \RuntimeException('Impossible d\'ouvrir l\'archive d\'export.');
            }
            $zip->addFromString('export.json', $json);
            $zip->close();

            $bytes = file_get_contents($tmp);
            if (false === $bytes) {
                throw new \RuntimeException('Lecture de l\'archive d\'export impossible.');
            }

            return $bytes;
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }
}
