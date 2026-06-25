<?php

declare(strict_types=1);

// Garde-fou de couverture (décision D8 : backend >= 80 % de lignes).
// Usage : php bin/coverage-gate.php [clover.xml] [seuil]
$file = $argv[1] ?? 'var/coverage/clover.xml';
$min = (float) ($argv[2] ?? '80');

if (!is_file($file)) {
    fwrite(STDERR, "Rapport de couverture introuvable : {$file}\n");
    exit(2);
}

$xml = simplexml_load_file($file);
if (false === $xml) {
    fwrite(STDERR, "Clover illisible : {$file}\n");
    exit(2);
}

$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$pct = $statements > 0 ? 100 * $covered / $statements : 100.0;

printf("Couverture backend (lignes) : %.1f%% (%d/%d) — seuil %.0f%%\n", $pct, $covered, $statements, $min);

if ($pct + 1e-9 < $min) {
    fwrite(STDERR, "ÉCHEC : couverture sous le seuil.\n");
    exit(1);
}
echo "OK : seuil atteint.\n";
