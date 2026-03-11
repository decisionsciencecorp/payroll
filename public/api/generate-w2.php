<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

initializeDatabase();

$year = isset($_GET['year']) ? (int)$_GET['year'] : null;
if (!$year) {
    header('Content-Type: application/json');
    jsonError('Query parameter year= required', 400);
    exit;
}

$apiKey = getApiKey();
if (!$apiKey || !validateApiKey($apiKey)) {
    header('Content-Type: application/json');
    jsonError('Invalid or missing API key', 401);
    exit;
}

$db = getDbConnection();
$company = $db->querySingle("SELECT employer_name, employer_ein, employer_address_line1, employer_address_line2, employer_city, employer_state, employer_zip FROM company_settings WHERE id = 1", true);
if (!$company || empty($company['employer_name']) || empty($company['employer_ein'])) {
    header('Content-Type: application/json');
    jsonError('Employer name and EIN must be set in company settings', 400);
    exit;
}

// Employees with payroll in this year: get their last payroll row for YTD
$y = (string)$year;
$stmt = $db->prepare("
    SELECT e.id, e.full_name, e.ssn, e.address_line1, e.address_line2, e.city, e.state, e.zip,
           p.ytd_gross, p.ytd_federal_withheld, p.ytd_ss, p.ytd_medicare
    FROM payroll_history p
    JOIN employees e ON e.id = p.employee_id
    JOIN (SELECT employee_id, MAX(pay_date) as md FROM payroll_history WHERE strftime('%Y', pay_date) = :year GROUP BY employee_id) last ON last.employee_id = p.employee_id AND last.md = p.pay_date
    WHERE strftime('%Y', p.pay_date) = :year2
");
$stmt->bindValue(':year', $y, SQLITE3_TEXT);
$stmt->bindValue(':year2', $y, SQLITE3_TEXT);
$r = $stmt->execute();
$w2s = [];
while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
    if (empty($row['address_line1']) || empty($row['city']) || empty($row['state']) || empty($row['zip'])) {
        continue; // skip employees without address for W-2
    }
    $w2s[] = $row;
}

if (empty($w2s)) {
    header('Content-Type: application/json');
    jsonError('No employees with payroll and complete address for year ' . $year, 400);
    exit;
}

$fmt = function ($n) { return number_format((float)$n, 2); };
$ein = htmlspecialchars($company['employer_ein']);
$ename = htmlspecialchars($company['employer_name']);
$eaddr = trim(implode(', ', array_filter([
    $company['employer_address_line1'],
    $company['employer_address_line2'],
    $company['employer_city'],
    $company['employer_state'],
    $company['employer_zip']
])));

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="W2-' . $year . '.html"');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>W-2 <?= $year ?></title>
    <style>
        body { font-family: system-ui, sans-serif; font-size: 11px; }
        .w2 { border: 1px solid #000; width: 600px; margin: 1rem auto; padding: 0.5rem; page-break-after: always; }
        .w2:last-child { page-break-after: auto; }
        table { width: 100%; border-collapse: collapse; }
        .w2 td { padding: 2px 4px; border: 1px solid #ccc; }
        .box { font-weight: bold; }
        @media print { .w2 { margin: 0; } }
    </style>
</head>
<body>
<?php foreach ($w2s as $w): ?>
    <div class="w2">
        <p><strong>Form W-2 — Wage and Tax Statement — <?= $year ?></strong></p>
        <table>
            <tr><td class="box">Employer EIN</td><td><?= $ein ?></td><td>Employer name</td><td><?= $ename ?></td></tr>
            <tr><td>Employer address</td><td colspan="3"><?= htmlspecialchars($eaddr) ?></td></tr>
            <tr><td class="box">Employee SSN</td><td><?= htmlspecialchars($w['ssn']) ?></td><td>Employee name</td><td><?= htmlspecialchars($w['full_name']) ?></td></tr>
            <tr><td>Employee address</td><td colspan="3"><?= htmlspecialchars(trim(implode(', ', array_filter([$w['address_line1'], $w['address_line2'], $w['city'], $w['state'], $w['zip'])))) ?></td></tr>
            <tr><td>1 Wages</td><td>$<?= $fmt($w['ytd_gross']) ?></td><td>2 Federal withheld</td><td>$<?= $fmt($w['ytd_federal_withheld']) ?></td></tr>
            <tr><td>3 Social Security wages</td><td>$<?= $fmt($w['ytd_gross']) ?></td><td>4 Social Security withheld</td><td>$<?= $fmt($w['ytd_ss']) ?></td></tr>
            <tr><td>5 Medicare wages</td><td>$<?= $fmt($w['ytd_gross']) ?></td><td>6 Medicare withheld</td><td>$<?= $fmt($w['ytd_medicare']) ?></td></tr>
        </table>
    </div>
<?php endforeach; ?>
<p style="text-align: center;">Print or Save as PDF from your browser.</p>
</body>
</html>
