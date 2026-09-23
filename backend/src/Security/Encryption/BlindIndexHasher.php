<?php

declare(strict_types=1);

namespace App\Security\Encryption;

/**
 * Empreinte déterministe et keyée (HMAC-SHA256) pour les « blind index » — recherche/unicité
 * sur des champs chiffrés au repos sans déchiffrer (Lot 1 §5.1 : unicité d'une invitation
 * `pending` par `(family, email_hash)`).
 *
 * La clé (la même que le chiffrement applicatif, hors base) empêche un dump SQL brut de
 * reconstruire les e-mails par dictionnaire/rainbow table : le hash n'est pas inversible sans clé.
 */
final class BlindIndexHasher
{
    /** @var non-empty-string */
    private readonly string $key;

    public function __construct(string $key)
    {
        if ('' === $key) {
            throw new \InvalidArgumentException('La clé du blind index ne peut être vide.');
        }
        $this->key = $key;
    }

    /** Empreinte hex (64 caractères) d'une valeur normalisée. */
    public function hash(string $value): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($value)), $this->key);
    }

    /**
     * Empreinte d'une valeur **sensible à la casse** : un jeton FCM distingue majuscules et
     * minuscules, et deux jetons qui ne différeraient que par la casse sont deux appareils.
     */
    public function hashExact(string $value): string
    {
        return hash_hmac('sha256', $value, $this->key);
    }
}
