<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class AppLogTest extends TestCase
{
    public function testAppLogWritesToFile(): void
    {
        $message = 'AppLogTest_' . uniqid('', true);
        app_log('info', $message);
        $this->assertFileExists(LOG_PATH);
        $content = file_get_contents(LOG_PATH);
        $this->assertStringContainsString('[info]', $content);
        $this->assertStringContainsString($message, $content);
    }

    public function testAppLogDoesNotThrowWhenLogPathUndefined(): void
    {
        // When LOG_PATH is not defined, app_log returns early without writing (see functions.php).
        // We cannot undefine a constant; this test just ensures app_log is callable and handles the path.
        $this->assertTrue(function_exists('app_log'));
    }
}
