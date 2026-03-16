<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ValidateDateYmdTest extends TestCase
{
    public function testValidDate(): void
    {
        $this->assertTrue(validateDateYmd('2026-01-01'));
        $this->assertTrue(validateDateYmd('2025-12-31'));
        $this->assertTrue(validateDateYmd('2000-06-15'));
    }

    public function testInvalidFormat(): void
    {
        $this->assertFalse(validateDateYmd('01-01-2026'));
        $this->assertFalse(validateDateYmd('2026/01/01'));
        $this->assertFalse(validateDateYmd('not-a-date'));
    }

    public function testEmptyOrNonString(): void
    {
        $this->assertFalse(validateDateYmd(''));
        $this->assertFalse(validateDateYmd(null));
    }

    public function testInvalidDate(): void
    {
        $this->assertFalse(validateDateYmd('2026-13-01'));
        $this->assertFalse(validateDateYmd('2026-02-30'));
    }
}
