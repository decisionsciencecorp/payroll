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
if (!checkRateLimit('get_payroll:' . $apiKey . ':' . $ip, 60, 60)) {
    jsonError('Rate limit exceeded', 429);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) {
    jsonError('Query parameter id= required', 400);
    exit;
}

$db = getDbConnection();
$stmt = $db->prepare("SELECT p.*, e.full_name as employee_name, e.monthly_gross_salary FROM payroll_history p JOIN employees e ON e.id = p.employee_id WHERE p.id = :id");
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
if (!$row) {
    jsonError('Payroll record not found', 404);
    exit;
}

jsonSuccess(['payroll' => $row]);
