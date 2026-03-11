<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
initializeDatabase();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed. Use GET.', 405);
    exit;
}

$apiKey = getApiKey();
if (!$apiKey || !validateApiKey($apiKey)) {
    jsonError('Invalid or missing API key', 401);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit('list_emp:' . $apiKey . ':' . $ip, 60, 60)) {
    jsonError('Rate limit exceeded', 429);
    exit;
}

$limit = isset($_GET['limit']) ? min(500, max(1, (int)$_GET['limit'])) : 100;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

$db = getDbConnection();
$r = $db->query("SELECT id, full_name, ssn, filing_status, hire_date, monthly_gross_salary, created_at FROM employees ORDER BY id LIMIT $limit OFFSET $offset");
$employees = [];
while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
    $row['ssn'] = maskSsn($row['ssn']);
    $employees[] = $row;
}
$count = $db->querySingle("SELECT COUNT(*) FROM employees");

jsonSuccess(['employees' => $employees, 'count' => count($employees), 'total' => (int)$count]);
