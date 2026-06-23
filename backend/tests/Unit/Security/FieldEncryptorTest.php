<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\Encryption\FieldEncryptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FieldEncryptor::class)]
final class FieldEncryptorTest extends TestCase
{
    private function encryptor(): FieldEncryptor
    {
        return new FieldEncryptor(str_repeat("\x01", 32));
    }

    public function testRoundTrip(): void
    {
        $enc = $this->encryptor();
        $plain = 'Jean Dupont — 0612345678 — éàü';

        $cipher = $enc->encrypt($plain);

        self::assertNotNull($cipher);
        self::assertNotSame($plain, $cipher, 'Le ciphertext ne doit pas être le clair.');
        self::assertStringStartsWith('enc:v1:', (string) $cipher);
        self::assertSame($plain, $enc->decrypt($cipher));
    }

    public function testNullStaysNull(): void
    {
        $enc = $this->encryptor();
        self::assertNull($enc->encrypt(null));
        self::assertNull($enc->decrypt(null));
    }

    public function testCiphertextIsNonDeterministic(): void
    {
        $enc = $this->encryptor();
        // Nonce aléatoire : deux chiffrements du même clair diffèrent (pas d'oracle d'égalité).
        self::assertNotSame($enc->encrypt('secret'), $enc->encrypt('secret'));
    }

    public function testLegacyPlaintextPassthrough(): void
    {
        // Une valeur non préfixée (héritée) est renvoyée telle quelle.
        self::assertSame('clair', $this->encryptor()->decrypt('clair'));
    }

    public function testTamperedCiphertextIsRejected(): void
    {
        $enc = $this->encryptor();
        $cipher = (string) $enc->encrypt('intègre ?');
        $tampered = substr($cipher, 0, -2).'00';

        $this->expectException(\RuntimeException::class);
        $enc->decrypt($tampered);
    }

    public function testWrongKeyCannotDecrypt(): void
    {
        $cipher = $this->encryptor()->encrypt('secret');
        $other = new FieldEncryptor(str_repeat("\x02", 32));

        $this->expectException(\RuntimeException::class);
        $other->decrypt($cipher);
    }

    public function testInvalidKeyLengthRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FieldEncryptor('trop-court');
    }
}
