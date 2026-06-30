<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Child;
use App\Exception\ChildInputException;
use App\Service\Child\NoteDateValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Cohérence de note_date (§3.8) : pas dans le futur, pas avant la naissance.
 */
final class NoteDateValidatorTest extends TestCase
{
    private function validator(): NoteDateValidator
    {
        return new NoteDateValidator(new MockClock(new \DateTimeImmutable('2026-06-23 12:00', new \DateTimeZone('Europe/Paris'))));
    }

    private function child(?string $birth): Child
    {
        $c = new Child();
        $c->setBirthDate(null === $birth ? null : new \DateTimeImmutable($birth));

        return $c;
    }

    public function testTodayIsAccepted(): void
    {
        $this->validator()->validate(new \DateTimeImmutable('2026-06-23'), $this->child('2016-01-01'));
        $this->expectNotToPerformAssertions();
    }

    public function testFutureRejected(): void
    {
        $this->expectException(ChildInputException::class);
        $this->validator()->validate(new \DateTimeImmutable('2026-06-24'), $this->child(null));
    }

    public function testBeforeBirthRejected(): void
    {
        $this->expectException(ChildInputException::class);
        $this->validator()->validate(new \DateTimeImmutable('2015-12-31'), $this->child('2016-01-01'));
    }

    public function testEqualBirthAccepted(): void
    {
        $this->validator()->validate(new \DateTimeImmutable('2016-01-01'), $this->child('2016-01-01'));
        $this->expectNotToPerformAssertions();
    }

    public function testNoBirthDateOnlyFutureBoundApplies(): void
    {
        // birth_date null → toute date passée acceptée.
        $this->validator()->validate(new \DateTimeImmutable('1990-01-01'), $this->child(null));
        $this->expectNotToPerformAssertions();
    }
}
