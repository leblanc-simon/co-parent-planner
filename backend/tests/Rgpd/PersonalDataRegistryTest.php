<?php

declare(strict_types=1);

namespace App\Tests\Rgpd;

use App\Rgpd\PersonalDataRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test de cohérence du registre des données personnelles (cdc §7.1, US-RGPD-0) — bloquant en CI.
 */
#[CoversClass(PersonalDataRegistry::class)]
final class PersonalDataRegistryTest extends TestCase
{
    private const string REGISTRE_PATH = __DIR__.'/../../docs/registre-donnees.md';

    private function registreContent(): string
    {
        $content = file_get_contents(self::REGISTRE_PATH);
        self::assertNotFalse($content, 'docs/registre-donnees.md doit exister.');

        return $content;
    }

    public function testRegistryIsNotEmpty(): void
    {
        self::assertNotEmpty((new PersonalDataRegistry())->collect(), 'Au moins un champ personnel est attendu au Lot 0.');
    }

    public function testEveryPersonalFieldHasRegisterEntry(): void
    {
        $content = $this->registreContent();
        $missing = [];

        foreach ((new PersonalDataRegistry())->collect() as $entry) {
            if (!str_contains($content, $entry->key())) {
                $missing[] = $entry->key();
            }
        }

        self::assertSame([], $missing, \sprintf(
            "Champ(s) personnel(s) sans entrée dans docs/registre-donnees.md : %s.\n".
            'Ajoutez une ligne (clé `Entité.champ`) au registre (DoD du lot).',
            implode(', ', $missing),
        ));
    }

    public function testEveryEncryptedFieldIsMarkedPersonal(): void
    {
        $missing = (new PersonalDataRegistry())->encryptedFieldsMissingPersonalData();

        self::assertSame([], $missing, \sprintf(
            'Champ(s) chiffré(s) 🔒 sans attribut #[PersonalData] : %s. '.
            'Tout champ chiffré est personnel et doit être déclaré.',
            implode(', ', $missing),
        ));
    }
}
