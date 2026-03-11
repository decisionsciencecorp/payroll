<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class FormatDateTest extends TestCase
{
    public function testFormatsDate(): void
    {
        $this->assertSame('Jan 1, 2026', formatDate('2026-01-01'));
        $this->assertSame('Dec 31, 2025', formatDate('2025-12-31'));
    }

    public function testEmptyReturnsEmpty(): void
    {
        $this->assertSame('', formatDate(''));
        $this->assertSame('', formatDate(null));
    }
}
