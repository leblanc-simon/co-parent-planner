<?php

declare(strict_types=1);

namespace App\Rgpd;

use App\Attribute\PersonalData;
use App\Doctrine\Type\EncryptedStringType;
use App\Entity\User;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;

/**
 * Scanne les entités pour construire le **registre des données personnelles** (cdc §7.1).
 *
 * Source de vérité : les attributs {@see PersonalData} posés sur les propriétés d'entité.
 * Sert au test de cohérence CI (App\Tests\Rgpd\PersonalDataRegistryTest) qui garantit que :
 *  1. tout champ marqué personnel a une entrée dans `docs/registre-donnees.md` ;
 *  2. tout champ **chiffré** 🔒 (type Doctrine encrypted_string) est marqué personnel.
 */
final class PersonalDataRegistry
{
    /**
     * @return list<PersonalDataEntry>
     */
    public function collect(): array
    {
        $entries = [];
        foreach ($this->entityClasses() as $class) {
            $rc = new \ReflectionClass($class);
            foreach ($rc->getProperties() as $property) {
                $attrs = $property->getAttributes(PersonalData::class);
                if ([] === $attrs) {
                    continue;
                }
                $entries[] = new PersonalDataEntry(
                    $rc->getShortName(),
                    $property->getName(),
                    $attrs[0]->newInstance(),
                );
            }
        }

        usort($entries, static fn (PersonalDataEntry $a, PersonalDataEntry $b) => $a->key() <=> $b->key());

        return $entries;
    }

    /**
     * Champs dont la colonne est chiffrée (encrypted_string) mais qui ne portent PAS #[PersonalData].
     * Un dump SQL chiffré sans entrée de registre serait une fuite de conformité → échec CI.
     *
     * @return list<string> clés « Entité.champ »
     */
    public function encryptedFieldsMissingPersonalData(): array
    {
        $missing = [];
        foreach ($this->entityClasses() as $class) {
            $rc = new \ReflectionClass($class);
            foreach ($rc->getProperties() as $property) {
                if (!$this->isEncryptedColumn($property)) {
                    continue;
                }
                if ([] === $property->getAttributes(PersonalData::class)) {
                    $missing[] = $rc->getShortName().'.'.$property->getName();
                }
            }
        }

        return $missing;
    }

    private function isEncryptedColumn(\ReflectionProperty $property): bool
    {
        foreach ($property->getAttributes(Column::class) as $attr) {
            /** @var Column $column */
            $column = $attr->newInstance();
            if (EncryptedStringType::NAME === $column->type) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<class-string>
     */
    private function entityClasses(): array
    {
        $dir = \dirname((string) (new \ReflectionClass(User::class))->getFileName());
        $classes = [];
        foreach (glob($dir.'/*.php') ?: [] as $file) {
            $fqcn = 'App\\Entity\\'.basename($file, '.php');
            if (!class_exists($fqcn)) {
                continue;
            }
            $rc = new \ReflectionClass($fqcn);
            if ([] !== $rc->getAttributes(Entity::class)) {
                $classes[] = $fqcn;
            }
        }

        return $classes;
    }
}
