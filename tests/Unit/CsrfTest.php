<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Bootstrap already loaded config and started session
require_once __DIR__ . '/../../public/includes/csrf.php';

class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['csrf_token']);
    }

    public function testGenerateCsrfToken(): void
    {
        $t1 = generateCsrfToken();
        $t2 = getCsrfToken();
        $this->assertNotEmpty($t1);
        $this->assertSame($t1, $t2);
        $this->assertTrue(verifyCsrfToken($t1));
        $this->assertFalse(verifyCsrfToken('wrong'));
    }

    public function testCsrfFieldContainsToken(): void
    {
        $html = csrfField();
        $this->assertStringContainsString('csrf_token', $html);
        $this->assertStringContainsString('value="', $html);
    }
}
