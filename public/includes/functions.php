<?php
require_once __DIR__ . '/config.php';

function getApiKey() {
    if (!empty($_SERVER['HTTP_X_API_KEY'])) {
        return trim($_SERVER['HTTP_X_API_KEY']);
    }
    if (isset($_GET['api_key'])) {
        return trim($_GET['api_key']);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['api_key'])) {
            return trim($_POST['api_key']);
        }
        $input = file_get_contents('php://input');
        if ($input) {
            $data = json_decode($input, true);
            if (isset($data['api_key'])) {
                return trim($data['api_key']);
            }
        }
    }
    return null;
}

function validateApiKey($apiKey) {
    if (empty($apiKey)) return false;
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT id FROM api_keys WHERE api_key = :key");
    $stmt->bindValue(':key', $apiKey, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        $up = $db->prepare("UPDATE api_keys SET last_used = CURRENT_TIMESTAMP WHERE id = :id");
        $up->bindValue(':id', $row['id'], SQLITE3_INTEGER);
        $up->execute();
        return true;
    }
    return false;
}

function getApiKeyName($apiKey) {
    if (empty($apiKey)) return null;
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT key_name FROM api_keys WHERE api_key = :key");
    $stmt->bindValue(':key', $apiKey, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row ? $row['key_name'] : null;
}

function checkRateLimit($rateKey, $limit = 60, $windowSeconds = 60) {
    $db = getDbConnection();
    $now = time();
    $stmt = $db->prepare("SELECT window_start, count FROM api_rate_limits WHERE rate_key = :rate_key");
    $stmt->bindValue(':rate_key', $rateKey, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if (!$row) {
        $ins = $db->prepare("INSERT INTO api_rate_limits (rate_key, window_start, count) VALUES (:rate_key, :window_start, :count)");
        $ins->bindValue(':rate_key', $rateKey, SQLITE3_TEXT);
        $ins->bindValue(':window_start', $now, SQLITE3_INTEGER);
        $ins->bindValue(':count', 1, SQLITE3_INTEGER);
        $ins->execute();
        return true;
    }
    $windowStart = (int)$row['window_start'];
    $count = (int)$row['count'];
    if ($now - $windowStart >= $windowSeconds) {
        $reset = $db->prepare("UPDATE api_rate_limits SET window_start = :window_start, count = :count WHERE rate_key = :rate_key");
        $reset->bindValue(':window_start', $now, SQLITE3_INTEGER);
        $reset->bindValue(':count', 1, SQLITE3_INTEGER);
        $reset->bindValue(':rate_key', $rateKey, SQLITE3_TEXT);
        $reset->execute();
        return true;
    }
    if ($count >= $limit) return false;
    $up = $db->prepare("UPDATE api_rate_limits SET count = count + 1 WHERE rate_key = :rate_key");
    $up->bindValue(':rate_key', $rateKey, SQLITE3_TEXT);
    $up->execute();
    return true;
}

function jsonSuccess($data = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => true], $data));
}

function jsonError($message, $code = 400) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
}

function maskSsn($ssn) {
    $digits = preg_replace('/\D/', '', $ssn);
    if (strlen($digits) < 4) return '***-**-****';
    return '***-**-' . substr($digits, -4);
}

/** Return first API key for admin UI server-side API calls (e.g. run payroll, create employee). */
function getApiKeyForAdmin() {
    $db = getDbConnection();
    $r = $db->query("SELECT api_key FROM api_keys LIMIT 1");
    $row = $r->fetchArray(SQLITE3_ASSOC);
    return $row ? $row['api_key'] : null;
}

// API key management (for admin)
function getAllApiKeys() {
    $db = getDbConnection();
    $r = $db->query("SELECT id, key_name, api_key, created_at, last_used FROM api_keys ORDER BY created_at DESC");
    $out = [];
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) $out[] = $row;
    return $out;
}

function createApiKey($keyName) {
    $db = getDbConnection();
    $apiKey = bin2hex(random_bytes(32));
    $stmt = $db->prepare("INSERT INTO api_keys (key_name, api_key) VALUES (:name, :key)");
    $stmt->bindValue(':name', $keyName, SQLITE3_TEXT);
    $stmt->bindValue(':key', $apiKey, SQLITE3_TEXT);
    $stmt->execute();
    return $apiKey;
}

function deleteApiKey($id) {
    $db = getDbConnection();
    $stmt = $db->prepare("DELETE FROM api_keys WHERE id = :id");
    $stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
    $stmt->execute();
}

function formatDate($date) {
    if (empty($date)) return '';
    return date('M j, Y', strtotime($date));
}

/**
 * Payroll calculation for one employee for one period.
 * Returns [ federal_withholding, employee_ss, employee_medicare, employer_ss, employer_medicare, net_pay, ytd_gross, ytd_federal_withheld, ytd_ss, ytd_medicare ]
 */
function calculatePayrollForEmployee($employee, $config, $ytdGross, $ytdFederal, $ytdSs, $ytdMedicare) {
    $gross = (float)$employee['monthly_gross_salary'];
    $step4c = isset($employee['step4c_extra_withholding']) ? (float)$employee['step4c_extra_withholding'] : 0;

    $bracketKey = $employee['filing_status'] === 'Single' ? 'single' : ($employee['filing_status'] === 'Head of Household' ? 'head_of_household' : 'married');
    $brackets = $config['brackets'][$bracketKey] ?? [];
    $federal = 0;
    if (!empty($brackets)) {
        $taxable = $gross;
        $prevMax = 0;
        foreach ($brackets as $b) {
            $min = (float)$b['min'];
            $max = (float)$b['max'];
            $rate = (float)$b['rate'];
            $amountInBracket = min($taxable, $max) - min($taxable, $min);
            if ($amountInBracket > 0) $federal += $amountInBracket * $rate;
            $prevMax = $max;
        }
    }
    $federal += $step4c;

    $ssWageBase = (float)($config['ss_wage_base'] ?? 184500);
    $ssRate = (float)($config['fica_ss_rate'] ?? 0.062);
    $remainingSs = max(0, $ssWageBase - $ytdGross);
    $ssWage = min($gross, $remainingSs);
    $employeeSs = round($ssWage * $ssRate, 2);
    $employerSs = $employeeSs;

    $medRate = (float)($config['fica_medicare_rate'] ?? 0.0145);
    $employeeMedicare = round($gross * $medRate, 2);
    $addMedRate = (float)($config['additional_medicare_rate'] ?? 0);
    $thresholds = $config['additional_medicare_thresholds'] ?? [];
    $thKey = $employee['filing_status'] === 'Married filing separately' ? 'married_filing_separately' : ($employee['filing_status'] === 'Married filing jointly' ? 'married_filing_jointly' : 'single');
    $threshold = isset($thresholds[$thKey]) ? (float)$thresholds[$thKey] : 200000;
    $addMed = 0;
    if ($addMedRate > 0 && ($ytdGross + $gross) > $threshold) {
        $overThreshold = $ytdGross + $gross - $threshold;
        $taxableThisPeriod = min($gross, $overThreshold);
        $addMed = round(max(0, $taxableThisPeriod) * $addMedRate, 2);
    }
    $employeeMedicare += $addMed;
    $employerMedicare = round($gross * $medRate, 2);

    $net = round($gross - $federal - $employeeSs - $employeeMedicare, 2);
    return [
        'federal_withholding' => round($federal, 2),
        'employee_ss' => $employeeSs,
        'employee_medicare' => $employeeMedicare,
        'employer_ss' => $employerSs,
        'employer_medicare' => $employerMedicare,
        'net_pay' => $net,
        'ytd_gross' => round($ytdGross + $gross, 2),
        'ytd_federal_withheld' => round($ytdFederal + $federal, 2),
        'ytd_ss' => round($ytdSs + $employeeSs, 2),
        'ytd_medicare' => round($ytdMedicare + $employeeMedicare, 2),
    ];
}
