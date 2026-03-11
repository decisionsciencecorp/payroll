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
if (!checkRateLimit('get:' . $apiKey . ':' . $ip, 60, 60)) {
    jsonError('Rate limit exceeded', 429);
    exit;
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : null;
if (!$year) {
    jsonError('Query parameter year= required', 400);
    exit;
}

$db = getDbConnection();
$stmt = $db->prepare("SELECT config_json FROM tax_config WHERE tax_year = :year");
$stmt->bindValue(':year', $year, SQLITE3_INTEGER);
$r = $stmt->execute();
$row = $r->fetchArray(SQLITE3_ASSOC);
if (!$row) {
    jsonError('Tax config not found for year ' . $year, 404);
    exit;
}

$config = json_decode($row['config_json'], true);
jsonSuccess(['year' => $year, 'config' => $config]);
