<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Enum\NotificationType;
use App\Service\Notification\Push\PushComposer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(PushComposer::class)]
final class PushComposerTest extends TestCase
{
    private function composer(string $rendered): PushComposer
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn($rendered);

        return new PushComposer($translator);
    }

    /** Une coupe au milieu d'un emoji ou d'une lettre accentuée casserait l'affichage natif. */
    public function testTruncationRespectsGraphemes(): void
    {
        $long = str_repeat('é', 150).'👨‍👩‍👧'.str_repeat('à', 100);
        $content = $this->composer($long)->compose(NotificationType::ExchangeMessage, 'exchange_message:f', null, [], 'fr-FR');

        self::assertSame(180, grapheme_strlen($content->body));
        self::assertStringEndsWith('…', $content->body);
        self::assertSame(80, grapheme_strlen($content->title));
        self::assertTrue(mb_check_encoding($content->body, 'UTF-8'));
    }

    public function testShortTextIsKeptAsIs(): void
    {
        $content = $this->composer('Nouveau message')->compose(NotificationType::ExchangeMessage, 'exchange_message:f', 'fam-1', ['exchangeId' => 'ex', 'title' => 'T', 'membershipId' => 'm'], 'fr-FR');

        self::assertSame('Nouveau message', $content->title);
        // Seuls les identifiants de routage passent ; `title`/`membershipId` restent côté serveur.
        self::assertSame(['eventId' => 'exchange_message:f', 'type' => 'exchange_message', 'category' => 'exchanges', 'familyId' => 'fam-1', 'exchangeId' => 'ex'], $content->data);
        self::assertSame('exchange_message:f', $content->collapseKey);
    }
}
