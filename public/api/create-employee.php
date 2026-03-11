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
if (!checkRateLimit('create_emp:' . $apiKey . ':' . $ip, 60, 60)) {
    jsonError('Rate limit exceeded', 429);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true) ?: [];
$statuses = ['Single', 'Married filing jointly', 'Married filing separately', 'Head of Household'];

if (empty($data['full_name']) || empty($data['ssn']) || empty($data['filing_status']) || empty($data['hire_date']) || !isset($data['monthly_gross_salary'])) {
    jsonError('Required: full_name, ssn, filing_status, hire_date, monthly_gross_salary', 400);
    exit;
}
if (!in_array($data['filing_status'], $statuses, true)) {
    jsonError('filing_status must be one of: ' . implode(', ', $statuses), 400);
    exit;
}

$db = getDbConnection();
$stmt = $db->prepare("
    INSERT INTO employees (full_name, ssn, filing_status, step4a_other_income, step4b_deductions, step4c_extra_withholding, hire_date, monthly_gross_salary, i9_completed_at, address_line1, address_line2, city, state, zip)
    VALUES (:fn, :ssn, :fs, :s4a, :s4b, :s4c, :hire, :sal, :i9, :a1, :a2, :city, :state, :zip)
");
$stmt->bindValue(':fn', trim($data['full_name']), SQLITE3_TEXT);
$stmt->bindValue(':ssn', preg_replace('/\D/', '', $data['ssn']), SQLITE3_TEXT);
$stmt->bindValue(':fs', $data['filing_status'], SQLITE3_TEXT);
$stmt->bindValue(':s4a', isset($data['step4a_other_income']) ? (float)$data['step4a_other_income'] : null, SQLITE3_FLOAT);
$stmt->bindValue(':s4b', isset($data['step4b_deductions']) ? (float)$data['step4b_deductions'] : null, SQLITE3_FLOAT);
$stmt->bindValue(':s4c', isset($data['step4c_extra_withholding']) ? (float)$data['step4c_extra_withholding'] : null, SQLITE3_FLOAT);
$stmt->bindValue(':hire', $data['hire_date'], SQLITE3_TEXT);
$stmt->bindValue(':sal', (float)$data['monthly_gross_salary'], SQLITE3_FLOAT);
$stmt->bindValue(':i9', !empty($data['i9_completed_at']) ? $data['i9_completed_at'] : null, SQLITE3_TEXT);
$stmt->bindValue(':a1', !empty($data['address_line1']) ? $data['address_line1'] : null, SQLITE3_TEXT);
$stmt->bindValue(':a2', !empty($data['address_line2']) ? $data['address_line2'] : null, SQLITE3_TEXT);
$stmt->bindValue(':city', !empty($data['city']) ? $data['city'] : null, SQLITE3_TEXT);
$stmt->bindValue(':state', !empty($data['state']) ? $data['state'] : null, SQLITE3_TEXT);
$stmt->bindValue(':zip', !empty($data['zip']) ? $data['zip'] : null, SQLITE3_TEXT);
$stmt->execute();
$id = $db->lastInsertRowID();

$r = $db->prepare("SELECT * FROM employees WHERE id = :id");
$r->bindValue(':id', $id, SQLITE3_INTEGER);
$row = $r->execute()->fetchArray(SQLITE3_ASSOC);
$employee = [];
foreach ($row as $k => $v) $employee[$k] = $v;

http_response_code(201);
jsonSuccess(['message' => 'Employee created', 'employee' => $employee]);
