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
if (!checkRateLimit('get_emp:' . $apiKey . ':' . $ip, 60, 60)) {
    jsonError('Rate limit exceeded', 429);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) {
    jsonError('Query parameter id= required', 400);
    exit;
}

$db = getDbConnection();
$stmt = $db->prepare("SELECT * FROM employees WHERE id = :id");
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$r = $stmt->execute();
$employee = $r->fetchArray(SQLITE3_ASSOC);
if (!$employee) {
    jsonError('Employee not found', 404);
    exit;
}

jsonSuccess(['employee' => $employee]);
