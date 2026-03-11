<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class MaskSsnTest extends TestCase
{
    public function testMasksFullSsn(): void
    {
        $this->assertSame('***-**-6789', maskSsn('123-45-6789'));
        $this->assertSame('***-**-6789', maskSsn('123456789'));
    }

    public function testShortSsnReturnsFullMask(): void
    {
        $this->assertSame('***-**-****', maskSsn('123'));
        $this->assertSame('***-**-****', maskSsn(''));
    }

    public function testExactlyFourDigits(): void
    {
        $this->assertSame('***-**-1234', maskSsn('1234'));
    }
}
