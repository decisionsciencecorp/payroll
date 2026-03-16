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
        // Newest first (ORDER BY created_at DESC); find the key we just created
        $created = null;
        foreach ($all2 as $row) {
            if ($row['key_name'] === 'For delete test') {
                $created = $row;
                break;
            }
        }
        $this->assertNotNull($created, 'Created key should appear in list');
        $this->assertSame('For delete test', $created['key_name']);
        deleteApiKey($created['id']);
        $this->assertCount($countBefore, getAllApiKeys());
    }

    public function testCheckRateLimitAllowsWithinLimit(): void
    {
        $key = 'test_limit_' . uniqid();
        $this->assertTrue(checkRateLimit($key, 60, 60));
        $this->assertTrue(checkRateLimit($key, 60, 60));
    }
}
