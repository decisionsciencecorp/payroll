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
if (!checkRateLimit('list_payroll:' . $apiKey . ':' . $ip, 60, 60)) {
    jsonError('Rate limit exceeded', 429);
    exit;
}

$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
$from = $_GET['pay_date_from'] ?? '';
$to = $_GET['pay_date_to'] ?? '';
if ($from !== '' && !validateDateYmd($from)) {
    jsonError('pay_date_from must be a valid Y-m-d date', 400);
    exit;
}
if ($to !== '' && !validateDateYmd($to)) {
    jsonError('pay_date_to must be a valid Y-m-d date', 400);
    exit;
}
$limit = isset($_GET['limit']) ? min(500, max(1, (int)$_GET['limit'])) : 100;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

$db = getDbConnection();
$where = ['1=1'];
$params = [];
if ($employeeId) { $where[] = 'p.employee_id = :eid'; $params[':eid'] = $employeeId; }
if ($from) { $where[] = 'p.pay_date >= :from'; $params[':from'] = $from; }
if ($to) { $where[] = 'p.pay_date <= :to'; $params[':to'] = $to; }
$params[':limit'] = $limit;
$params[':offset'] = $offset;
$sql = "SELECT p.*, e.full_name as employee_name FROM payroll_history p JOIN employees e ON e.id = p.employee_id WHERE " . implode(' AND ', $where) . " ORDER BY p.pay_date DESC, p.id DESC LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $type = ($k === ':limit' || $k === ':offset' || $k === ':eid') ? SQLITE3_INTEGER : SQLITE3_TEXT;
    $stmt->bindValue($k, $v, $type);
}
$r = $stmt->execute();
$payroll = [];
while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
    $payroll[] = $row;
}
$countSql = "SELECT COUNT(*) FROM payroll_history p WHERE " . implode(' AND ', $where);
$countParams = array_diff_key($params, [':limit' => 1, ':offset' => 1]);
if ($countParams) {
    $stmt = $db->prepare($countSql);
    foreach ($countParams as $k => $v) {
        $stmt->bindValue($k, $v, $k === ':eid' ? SQLITE3_INTEGER : SQLITE3_TEXT);
    }
    $total = $stmt->execute()->fetchArray(SQLITE3_NUM)[0];
} else {
    $total = $db->querySingle($countSql);
}

jsonSuccess(['payroll' => $payroll, 'count' => count($payroll), 'total' => (int)$total]);
