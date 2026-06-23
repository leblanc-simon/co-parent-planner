<?php

declare(strict_types=1);

namespace App\Security\Encryption;

/**
 * Chiffrement applicatif des champs marqués 🔒 (cdc §5.3, invariant CLAUDE.md).
 *
 * AES-256-GCM (authentifié). La clé (32 octets) provient de l'environnement
 * (`APP_ENCRYPTION_KEY`, base64) et n'est JAMAIS stockée en base. Un dump SQL brut ne
 * révèle donc ni identités ni contenus.
 *
 * Format stocké : `enc:v1:` . base64( nonce(12) || tag(16) || ciphertext ).
 * Une valeur non préfixée est renvoyée telle quelle (tolérance aux données héritées / claires).
 */
final class FieldEncryptor
{
    private const string CIPHER = 'aes-256-gcm';
    private const string PREFIX = 'enc:v1:';
    private const int NONCE_LEN = 12;
    private const int TAG_LEN = 16;

    /** @var non-empty-string clé brute de 32 octets */
    private readonly string $key;

    public function __construct(string $key)
    {
        if (32 !== \strlen($key)) {
            throw new \InvalidArgumentException('La clé de chiffrement doit faire exactement 32 octets (256 bits).');
        }
        $this->key = $key;
    }

    /**
     * Instancie depuis l'environnement, pour les contextes hors conteneur (types Doctrine).
     */
    public static function fromEnv(): self
    {
        $b64 = $_SERVER['APP_ENCRYPTION_KEY'] ?? $_ENV['APP_ENCRYPTION_KEY'] ?? '';
        if (!\is_string($b64) || '' === $b64) {
            throw new \RuntimeException('APP_ENCRYPTION_KEY absente : démarrage refusé (fail-fast, cdc §4.10).');
        }
        $raw = base64_decode($b64, true);
        if (false === $raw) {
            throw new \RuntimeException('APP_ENCRYPTION_KEY invalide (base64 attendu).');
        }

        return new self($raw);
    }

    public function encrypt(?string $plaintext): ?string
    {
        if (null === $plaintext) {
            return null;
        }

        $nonce = random_bytes(self::NONCE_LEN);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            \OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LEN,
        );

        if (false === $ciphertext) {
            throw new \RuntimeException('Échec du chiffrement AES-256-GCM.');
        }

        return self::PREFIX.base64_encode($nonce.$tag.$ciphertext);
    }

    public function decrypt(?string $stored): ?string
    {
        if (null === $stored) {
            return null;
        }
        if (!str_starts_with($stored, self::PREFIX)) {
            return $stored; // valeur héritée non chiffrée
        }

        $raw = base64_decode(substr($stored, \strlen(self::PREFIX)), true);
        if (false === $raw || \strlen($raw) < self::NONCE_LEN + self::TAG_LEN) {
            throw new \RuntimeException('Donnée chiffrée corrompue.');
        }

        $nonce = substr($raw, 0, self::NONCE_LEN);
        $tag = substr($raw, self::NONCE_LEN, self::TAG_LEN);
        $ciphertext = substr($raw, self::NONCE_LEN + self::TAG_LEN);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            \OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );

        if (false === $plaintext) {
            throw new \RuntimeException('Échec du déchiffrement (clé invalide ou donnée altérée).');
        }

        return $plaintext;
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }
}
