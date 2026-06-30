<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Exception\ChildInputException;
use App\Service\Child\InfoNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Normalisation des informations pratiques (§3.2, §4.5).
 */
final class InfoNormalizerTest extends TestCase
{
    private InfoNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new InfoNormalizer();
    }

    public function testTrimsAndDropsEmptyLines(): void
    {
        $result = $this->normalizer->normalize([
            ['label' => '  Pointure ', 'value' => ' 34 '],
            ['label' => '', 'value' => ''],
        ]);

        self::assertSame([['label' => 'Pointure', 'value' => '34']], $result);
    }

    public function testLabelOnlyIsTolerated(): void
    {
        $result = $this->normalizer->normalize([['label' => 'Note', 'value' => '']]);
        self::assertSame([['label' => 'Note', 'value' => '']], $result);
    }

    public function testValueWithoutLabelRejected(): void
    {
        $this->expectException(ChildInputException::class);
        $this->normalizer->normalize([['label' => '', 'value' => 'orpheline']]);
    }

    public function testTooManyItemsRejected(): void
    {
        $items = array_fill(0, 51, ['label' => 'x', 'value' => 'y']);
        $this->expectException(ChildInputException::class);
        $this->normalizer->normalize($items);
    }

    public function testPreservesOrder(): void
    {
        $result = $this->normalizer->normalize([
            ['label' => 'B', 'value' => '2'],
            ['label' => 'A', 'value' => '1'],
        ]);
        self::assertSame('B', $result[0]['label']);
        self::assertSame('A', $result[1]['label']);
    }
}
