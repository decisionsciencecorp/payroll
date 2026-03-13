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
if (!checkRateLimit('run_payroll:' . $apiKey . ':' . $ip, 10, 60)) {
    jsonError('Rate limit exceeded', 429);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true) ?: [];
$start = $data['pay_period_start'] ?? '';
$end = $data['pay_period_end'] ?? '';
$payDate = $data['pay_date'] ?? '';
if (!$start || !$end || !$payDate) {
    jsonError('Required: pay_period_start, pay_period_end, pay_date', 400);
    exit;
}
if (!validateDateYmd($start) || !validateDateYmd($end) || !validateDateYmd($payDate)) {
    jsonError('pay_period_start, pay_period_end, and pay_date must be valid Y-m-d dates', 400);
    exit;
}
if (strtotime($start) > strtotime($end)) {
    jsonError('pay_period_start must be before or equal to pay_period_end', 400);
    exit;
}

$year = (int)date('Y', strtotime($payDate));
$db = getDbConnection();
$stmt = $db->prepare("SELECT config_json FROM tax_config WHERE tax_year = :y");
$stmt->bindValue(':y', $year, SQLITE3_INTEGER);
$r = $stmt->execute();
$row = $r->fetchArray(SQLITE3_ASSOC);
if (!$row) {
    jsonError("No tax config for year $year", 400);
    exit;
}
$config = json_decode($row['config_json'], true);

$employeeIds = isset($data['employee_ids']) && is_array($data['employee_ids']) ? array_map('intval', $data['employee_ids']) : null;
if ($employeeIds !== null && empty($employeeIds)) {
    jsonError('employee_ids must be non-empty if provided', 400);
    exit;
}

$db->exec('BEGIN TRANSACTION');
try {
    if ($employeeIds === null) {
        $empResult = $db->query("SELECT * FROM employees");
    } else {
        $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
        $empResult = $db->prepare("SELECT * FROM employees WHERE id IN ($placeholders)");
        for ($i = 0; $i < count($employeeIds); $i++) $empResult->bindValue($i + 1, $employeeIds[$i], SQLITE3_INTEGER);
        $empResult = $empResult->execute();
    }
    $employees = [];
    while ($e = $empResult->fetchArray(SQLITE3_ASSOC)) $employees[] = $e;

    $insert = $db->prepare("
        INSERT INTO payroll_history (employee_id, pay_period_start, pay_period_end, pay_date, gross_pay, federal_withholding, employee_ss, employee_medicare, employer_ss, employer_medicare, net_pay, ytd_gross, ytd_federal_withheld, ytd_ss, ytd_medicare)
        VALUES (:eid, :start, :end, :pdate, :gross, :fed, :ess, :emed, :erss, :ermed, :net, :ytdg, :ytdf, :ytds, :ytdm)
    ");
    $checkDup = $db->prepare("SELECT id FROM payroll_history WHERE employee_id = :eid AND pay_date = :pdate");
    $getYtd = $db->prepare("SELECT ytd_gross, ytd_federal_withheld, ytd_ss, ytd_medicare FROM payroll_history WHERE employee_id = :eid AND pay_date <= :pdate ORDER BY pay_date DESC LIMIT 1");
    $records = 0;
    foreach ($employees as $emp) {
        $checkDup->bindValue(':eid', $emp['id'], SQLITE3_INTEGER);
        $checkDup->bindValue(':pdate', $payDate, SQLITE3_TEXT);
        if ($checkDup->execute()->fetchArray(SQLITE3_ASSOC)) continue; // skip duplicate
        $getYtd->bindValue(':eid', $emp['id'], SQLITE3_INTEGER);
        $getYtd->bindValue(':pdate', $payDate, SQLITE3_TEXT);
        $ytdRow = $getYtd->execute()->fetchArray(SQLITE3_ASSOC);
        $ytdGross = $ytdRow ? (float)$ytdRow['ytd_gross'] : 0;
        $ytdFederal = $ytdRow ? (float)$ytdRow['ytd_federal_withheld'] : 0;
        $ytdSs = $ytdRow ? (float)$ytdRow['ytd_ss'] : 0;
        $ytdMedicare = $ytdRow ? (float)$ytdRow['ytd_medicare'] : 0;
        $calc = calculatePayrollForEmployee($emp, $config, $ytdGross, $ytdFederal, $ytdSs, $ytdMedicare);
        $gross = (float)$emp['monthly_gross_salary'];
        $insert->bindValue(':eid', $emp['id'], SQLITE3_INTEGER);
        $insert->bindValue(':start', $start, SQLITE3_TEXT);
        $insert->bindValue(':end', $end, SQLITE3_TEXT);
        $insert->bindValue(':pdate', $payDate, SQLITE3_TEXT);
        $insert->bindValue(':gross', $gross, SQLITE3_FLOAT);
        $insert->bindValue(':fed', $calc['federal_withholding'], SQLITE3_FLOAT);
        $insert->bindValue(':ess', $calc['employee_ss'], SQLITE3_FLOAT);
        $insert->bindValue(':emed', $calc['employee_medicare'], SQLITE3_FLOAT);
        $insert->bindValue(':erss', $calc['employer_ss'], SQLITE3_FLOAT);
        $insert->bindValue(':ermed', $calc['employer_medicare'], SQLITE3_FLOAT);
        $insert->bindValue(':net', $calc['net_pay'], SQLITE3_FLOAT);
        $insert->bindValue(':ytdg', $calc['ytd_gross'], SQLITE3_FLOAT);
        $insert->bindValue(':ytdf', $calc['ytd_federal_withheld'], SQLITE3_FLOAT);
        $insert->bindValue(':ytds', $calc['ytd_ss'], SQLITE3_FLOAT);
        $insert->bindValue(':ytdm', $calc['ytd_medicare'], SQLITE3_FLOAT);
        $insert->execute();
        $records++;
    }
    $db->exec('COMMIT');
} catch (Exception $e) {
    $db->exec('ROLLBACK');
    jsonError('Payroll run failed: ' . $e->getMessage(), 400);
    exit;
}

jsonSuccess([
    'message' => 'Payroll run completed',
    'pay_period_start' => $start,
    'pay_period_end' => $end,
    'pay_date' => $payDate,
    'records' => $records,
]);
