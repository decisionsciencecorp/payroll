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
if (!checkRateLimit('delete:' . $apiKey . ':' . $ip, 30, 60)) {
    jsonError('Rate limit exceeded', 429);
    exit;
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : null;
if (!$year) {
    jsonError('Query parameter year= required', 400);
    exit;
}

$db = getDbConnection();
$stmt = $db->prepare("DELETE FROM tax_config WHERE tax_year = :year");
$stmt->bindValue(':year', $year, SQLITE3_INTEGER);
$stmt->execute();
if ($db->changes() === 0) {
    jsonError('Tax config not found for year ' . $year, 404);
    exit;
}

jsonSuccess(['message' => "Tax bracket config removed for year $year", 'year' => $year]);
