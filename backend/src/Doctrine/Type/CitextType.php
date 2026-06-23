<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Type PostgreSQL `citext` (texte insensible à la casse) — utilisé pour `User.email` (cdc §5.2).
 *
 * Nécessite l'extension `citext` (créée par la première migration). Garantit l'unicité
 * insensible à la casse au niveau base, en complément de la normalisation applicative.
 */
final class CitextType extends Type
{
    public const string NAME = 'citext';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'CITEXT';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return null === $value ? null : (string) $value;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return null === $value ? null : (string) $value;
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
