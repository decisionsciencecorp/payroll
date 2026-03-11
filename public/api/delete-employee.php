<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
initializeDatabase();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    jsonError('Method not allowed. Use DELETE.', 405);
    exit;
}

$apiKey = getApiKey();
if (!$apiKey || !validateApiKey($apiKey)) {
    jsonError('Invalid or missing API key', 401);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit('delete_emp:' . $apiKey . ':' . $ip, 30, 60)) {
    jsonError('Rate limit exceeded', 429);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) {
    jsonError('Query parameter id= required', 400);
    exit;
}

$db = getDbConnection();
$stmt = $db->prepare("SELECT COUNT(*) as c FROM payroll_history WHERE employee_id = :id");
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
if ($row && (int)$row['c'] > 0) {
    jsonError('Cannot delete employee with payroll history', 409);
    exit;
}

$stmt = $db->prepare("DELETE FROM employees WHERE id = :id");
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$stmt->execute();
if ($db->changes() === 0) {
    jsonError('Employee not found', 404);
    exit;
}

jsonSuccess(['message' => 'Employee deleted']);
