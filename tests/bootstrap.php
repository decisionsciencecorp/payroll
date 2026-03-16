<?php
// Test bootstrap: set up test DB path so config and app use a temp SQLite DB.
$testDir = sys_get_temp_dir() . '/payroll_test_' . getmypid();
if (!is_dir($testDir)) {
    mkdir($testDir, 0755, true);
}
if (!is_dir($testDir . '/storage')) {
    mkdir($testDir . '/storage', 0755, true);
}
putenv('PAYROLL_TEST=1');
putenv('DB_PATH=' . $testDir . '/payroll.db');
putenv('STORAGE_PATH=' . $testDir . '/storage');

// Load app config and init DB (tables + default admin)
require_once __DIR__ . '/../public/includes/config.php';
initializeDatabase();

// Load functions for unit tests
require_once __DIR__ . '/../public/includes/functions.php';

// Create a persistent API key for integration tests (same DB used by built-in server)
$GLOBALS['payroll_test_api_key'] = createApiKey('phpunit');
$GLOBALS['payroll_test_db_path'] = $testDir . '/payroll.db';
