<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Auth uses session and config; load auth after bootstrap has set test DB
require_once __DIR__ . '/../../public/includes/auth.php';

class AuthTest extends TestCase
{
    public function testLoginWithDefaultAdmin(): void
    {
        $result = login('admin', 'admin');
        $this->assertTrue($result['success'] ?? false);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $result = login('admin', 'wrong');
        $this->assertFalse($result['success'] ?? true);
        $this->assertArrayHasKey('error', $result);
    }

    public function testGetCurrentUserAfterLogin(): void
    {
        login('admin', 'admin');
        $user = getCurrentUser();
        $this->assertIsArray($user);
        $this->assertSame('admin', $user['username'] ?? '');
    }

    public function testChangePassword(): void
    {
        login('admin', 'admin');
        $user = getCurrentUser();
        $this->assertNotNull($user);
        $wrong = changePassword((int) $user['id'], 'wrong', 'newpass');
        $this->assertFalse($wrong['success'] ?? true);
        $ok = changePassword((int) $user['id'], 'admin', 'admin'); // set back so other tests can login
        $this->assertTrue($ok['success'] ?? false);
    }

    public function testAddAdminUserAndDelete(): void
    {
        $result = addAdminUser('testuser_' . uniqid(), 'testpass123');
        $this->assertTrue($result['success'] ?? false);
        $result2 = addAdminUser('admin', 'any');
        $this->assertFalse($result2['success'] ?? true);
        $this->assertStringContainsString('already exists', $result2['error'] ?? '');
        $all = getAllAdminUsers();
        $this->assertGreaterThanOrEqual(2, count($all));
        $toDelete = null;
        foreach ($all as $u) {
            if ($u['username'] !== 'admin') {
                $toDelete = $u['id'];
                break;
            }
        }
        if ($toDelete !== null) {
            $del = deleteAdminUser($toDelete);
            $this->assertTrue($del['success'] ?? false);
        }
    }

    public function testCannotDeleteLastAdmin(): void
    {
        $all = getAllAdminUsers();
        $this->assertGreaterThanOrEqual(1, count($all));
        $keepId = $all[0]['id'];
        foreach ($all as $u) {
            if ((int) $u['id'] !== (int) $keepId) {
                deleteAdminUser((int) $u['id']);
            }
        }
        $result = deleteAdminUser($keepId);
        $this->assertFalse($result['success'] ?? true);
        $this->assertStringContainsString('last admin', $result['error'] ?? '');
    }
}
