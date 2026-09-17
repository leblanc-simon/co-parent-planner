<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Variante structurée de {@see EncryptedStringType} : une valeur PHP `array` est sérialisée en JSON
 * **puis** chiffrée (AES-256-GCM), et stockée en TEXT.
 *
 * Existe pour `Child.info` (cdc §5.2), la seule colonne `jsonb` qui porte du contenu personnel : la
 * laisser en JSON lisible revenait à publier les informations pratiques d'un mineur dans tout dump
 * SQL. La contrepartie assumée est la perte des opérateurs JSON de PostgreSQL sur cette colonne —
 * aucune requête n'en faisait usage, `info` n'étant lu qu'entier, par l'ORM.
 *
 * L'encryptor est résolu depuis l'environnement (les types Doctrine ne reçoivent pas d'injection),
 * en réutilisant celui de {@see EncryptedStringType} : une seule instance, une seule clé.
 */
final class EncryptedJsonType extends Type
{
    public const string NAME = 'encrypted_json';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        return EncryptedStringType::sharedEncryptor()->encrypt(
            json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
        );
    }

    /** @return array<array-key, mixed>|null */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        if (null === $value) {
            return null;
        }

        // `decrypt()` renvoie tel quel ce qui n'est pas préfixé `enc:v1:` : une ligne héritée écrite
        // en JSON clair se relit donc sans traitement particulier (et se réécrit chiffrée).
        $json = EncryptedStringType::sharedEncryptor()->decrypt((string) $value);
        if (null === $json || '' === $json) {
            return [];
        }

        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : [];
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
