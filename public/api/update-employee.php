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
if (!checkRateLimit('update_emp:' . $apiKey . ':' . $ip, 60, 60)) {
    jsonError('Rate limit exceeded', 429);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true) ?: [];
if (empty($data['id'])) {
    jsonError('id required', 400);
    exit;
}
$id = (int)$data['id'];
$statuses = ['Single', 'Married filing jointly', 'Married filing separately', 'Head of Household'];

$db = getDbConnection();
$stmt = $db->prepare("SELECT id FROM employees WHERE id = :id");
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
if (!$stmt->execute()->fetchArray(SQLITE3_ASSOC)) {
    jsonError('Employee not found', 404);
    exit;
}

$allowed = ['full_name','ssn','filing_status','step4a_other_income','step4b_deductions','step4c_extra_withholding','hire_date','monthly_gross_salary','i9_completed_at','address_line1','address_line2','city','state','zip'];
$updates = [];
$params = [':id' => $id];
foreach ($allowed as $f) {
    if (array_key_exists($f, $data)) {
        if ($f === 'filing_status' && !in_array($data[$f], $statuses, true)) continue;
        if ($f === 'ssn') $data[$f] = preg_replace('/\D/', '', $data[$f]);
        $updates[] = "$f = :$f";
        $params[":$f"] = $data[$f];
    }
}
if (empty($updates)) {
    jsonError('No updatable fields provided', 400);
    exit;
}
$params[':updated'] = date('Y-m-d H:i:s');
$updates[] = "updated_at = :updated";
$sql = "UPDATE employees SET " . implode(', ', $updates) . " WHERE id = :id";
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    if (is_int($v)) $stmt->bindValue($k, $v, SQLITE3_INTEGER);
    elseif (is_float($v)) $stmt->bindValue($k, $v, SQLITE3_FLOAT);
    else $stmt->bindValue($k, $v ?? '', SQLITE3_TEXT);
}
$stmt->execute();

$r = $db->prepare("SELECT * FROM employees WHERE id = :id");
$r->bindValue(':id', $id, SQLITE3_INTEGER);
$employee = $r->execute()->fetchArray(SQLITE3_ASSOC);
jsonSuccess(['message' => 'Employee updated', 'employee' => $employee]);
