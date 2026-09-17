<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use App\Security\Encryption\FieldEncryptor;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Type Doctrine pour les champs chiffrés au repos 🔒 (cdc §5.3).
 *
 * Stocké en TEXT chiffré (AES-256-GCM). Le chiffrement/déchiffrement est transparent côté ORM ;
 * un accès SQL brut ne voit que le ciphertext. L'encryptor est résolu depuis l'environnement
 * (les types Doctrine ne reçoivent pas d'injection de dépendances).
 */
final class EncryptedStringType extends Type
{
    public const string NAME = 'encrypted_string';

    private static ?FieldEncryptor $encryptor = null;

    /** Permet aux tests / au conteneur d'injecter un encryptor précis. */
    public static function setEncryptor(?FieldEncryptor $encryptor): void
    {
        self::$encryptor = $encryptor;
    }

    /**
     * Encryptor partagé par tous les types chiffrés (cf. {@see EncryptedJsonType}) : une seule
     * instance, donc une seule clé, quel que soit le nombre de types.
     */
    public static function sharedEncryptor(): FieldEncryptor
    {
        return self::$encryptor ??= FieldEncryptor::fromEnv();
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        return self::sharedEncryptor()->encrypt((string) $value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        return self::sharedEncryptor()->decrypt((string) $value);
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
