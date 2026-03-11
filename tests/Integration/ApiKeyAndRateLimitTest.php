<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ApiKeyAndRateLimitTest extends TestCase
{
    public function testCreateAndValidateApiKey(): void
    {
        $key = createApiKey('Test key');
        $this->assertNotEmpty($key);
        $this->assertTrue(validateApiKey($key));
        $this->assertSame('Test key', getApiKeyName($key));
        $this->assertFalse(validateApiKey('invalid-key'));
    }

    public function testGetAllApiKeys(): void
    {
        $all = getAllApiKeys();
        $this->assertIsArray($all);
        $countBefore = count($all);
        createApiKey('For delete test');
        $all2 = getAllApiKeys();
        $this->assertCount($countBefore + 1, $all2);
        $last = end($all2);
        $this->assertSame('For delete test', $last['key_name']);
        deleteApiKey($last['id']);
        $this->assertCount($countBefore, getAllApiKeys());
    }

    public function testCheckRateLimitAllowsWithinLimit(): void
    {
        $key = 'test_limit_' . uniqid();
        $this->assertTrue(checkRateLimit($key, 60, 60));
        $this->assertTrue(checkRateLimit($key, 60, 60));
    }
}
