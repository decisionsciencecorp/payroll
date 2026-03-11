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
if (!checkRateLimit('list:' . $apiKey . ':' . $ip, 60, 60)) {
    jsonError('Rate limit exceeded', 429);
    exit;
}

$db = getDbConnection();
$r = $db->query("SELECT tax_year FROM tax_config ORDER BY tax_year DESC");
$years = [];
while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
    $years[] = (int)$row['tax_year'];
}

jsonSuccess(['years' => $years, 'count' => count($years)]);
