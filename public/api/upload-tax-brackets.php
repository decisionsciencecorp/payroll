<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
initializeDatabase();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
    exit;
}

$apiKey = getApiKey();
if (!$apiKey || !validateApiKey($apiKey)) {
    jsonError('Invalid or missing API key', 401);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit('upload:' . $apiKey . ':' . $ip, 30, 60)) {
    jsonError('Rate limit exceeded', 429);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) {
    jsonError('Invalid or missing JSON body', 400);
    exit;
}

$year = isset($data['year']) ? (int)$data['year'] : null;
if (!$year || $year < 2000 || $year > 2100) {
    jsonError('Valid year (2000-2100) required', 400);
    exit;
}

$required = ['ss_wage_base', 'fica_ss_rate', 'fica_medicare_rate', 'additional_medicare_rate', 'additional_medicare_thresholds', 'brackets'];
foreach ($required as $k) {
    if (!isset($data[$k])) {
        jsonError("Missing required field: $k", 400);
        exit;
    }
}

$thresholds = $data['additional_medicare_thresholds'];
if (!is_array($thresholds) || !isset($thresholds['single'], $thresholds['married_filing_jointly'], $thresholds['married_filing_separately'])) {
    jsonError('additional_medicare_thresholds must have single, married_filing_jointly, married_filing_separately', 400);
    exit;
}

$brackets = $data['brackets'];
if (!is_array($brackets)) {
    jsonError('brackets must be an object with single, married, head_of_household arrays', 400);
    exit;
}
foreach (['single', 'married', 'head_of_household'] as $status) {
    if (!isset($brackets[$status]) || !is_array($brackets[$status])) {
        jsonError("brackets.$status must be a non-empty array", 400);
        exit;
    }
    foreach ($brackets[$status] as $b) {
        if (!isset($b['min'], $b['max'], $b['rate'])) {
            jsonError("Each bracket must have min, max, rate", 400);
            exit;
        }
    }
}

$json = json_encode($data);
$db = getDbConnection();
$stmt = $db->prepare("INSERT OR REPLACE INTO tax_config (tax_year, config_json) VALUES (:year, :json)");
$stmt->bindValue(':year', $year, SQLITE3_INTEGER);
$stmt->bindValue(':json', $json, SQLITE3_TEXT);
$stmt->execute();

jsonSuccess(['message' => "Tax bracket config saved for year $year", 'year' => $year]);
