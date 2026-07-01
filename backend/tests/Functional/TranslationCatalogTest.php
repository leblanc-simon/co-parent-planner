<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Garde-fou : le catalogue de traductions fr-FR doit se **parser** intégralement. Les tests
 * fonctionnels ne consomment pas le handler d'e-mail async (seul chemin qui chargeait le catalogue),
 * si bien qu'un YAML invalide passait inaperçu jusqu'à l'E2E — ce test le rattrape au niveau unitaire.
 */
final class TranslationCatalogTest extends KernelTestCase
{
    public function testFrenchCatalogParsesAndResolvesKeys(): void
    {
        self::bootKernel();
        $translator = self::getContainer()->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        // Forcer le chargement/parse du catalogue fr (lève si YAML invalide).
        foreach ([
            'agenda.error.recurrence_too_long',
            'agenda.error.not_found',
            'custody.error.not_found',
        ] as $key) {
            $message = $translator->trans($key, [], 'messages', 'fr');
            self::assertNotSame($key, $message, "Clé de traduction non résolue : {$key}");
        }
    }
}
