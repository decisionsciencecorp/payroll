<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

initializeDatabase();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) {
    header('Content-Type: application/json');
    jsonError('Query parameter id= required', 400);
    exit;
}

$apiKey = getApiKey();
if (!$apiKey || !validateApiKey($apiKey)) {
    header('Content-Type: application/json');
    jsonError('Invalid or missing API key', 401);
    exit;
}

$db = getDbConnection();
$stmt = $db->prepare("SELECT p.*, e.full_name, e.monthly_gross_salary FROM payroll_history p JOIN employees e ON e.id = p.employee_id WHERE p.id = :id");
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
if (!$row) {
    header('Content-Type: application/json');
    jsonError('Payroll record not found', 404);
    exit;
}

$logoPath = $db->querySingle("SELECT logo_path FROM company_settings WHERE id = 1");
$logoUrl = $logoPath ? (SITE_URL . '/api/logo-file.php') : '';

$fmt = function ($n) { return number_format((float)$n, 2); };
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pay stub — <?= htmlspecialchars($row['full_name']) ?> — <?= htmlspecialchars($row['pay_date']) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 600px; margin: 1rem auto; padding: 1rem; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: 0.35rem 0.5rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { font-weight: 600; color: #555; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .logo { max-height: 60px; max-width: 180px; }
        h1 { font-size: 1.25rem; margin: 0; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <?php if ($logoUrl): ?><img src="<?= htmlspecialchars($logoUrl) ?>" alt="" class="logo"><?php endif; ?>
            <h1>Pay stub</h1>
        </div>
        <div>Pay date: <strong><?= htmlspecialchars($row['pay_date']) ?></strong></div>
    </div>
    <p><strong><?= htmlspecialchars($row['full_name']) ?></strong></p>
    <p>Pay period: <?= htmlspecialchars($row['pay_period_start']) ?> – <?= htmlspecialchars($row['pay_period_end']) ?></p>
    <table>
        <tr><th>Gross pay</th><td>$<?= $fmt($row['gross_pay']) ?></td></tr>
        <tr><th>Federal withholding</th><td>-$<?= $fmt($row['federal_withholding']) ?></td></tr>
        <tr><th>Social Security</th><td>-$<?= $fmt($row['employee_ss']) ?></td></tr>
        <tr><th>Medicare</th><td>-$<?= $fmt($row['employee_medicare']) ?></td></tr>
        <tr><th>Net pay</th><td><strong>$<?= $fmt($row['net_pay']) ?></strong></td></tr>
    </table>
    <p style="font-size: 0.9rem; color: #666;">YTD gross: $<?= $fmt($row['ytd_gross']) ?> | YTD federal: $<?= $fmt($row['ytd_federal_withheld']) ?> | YTD SS: $<?= $fmt($row['ytd_ss']) ?> | YTD Medicare: $<?= $fmt($row['ytd_medicare']) ?></p>
    <p style="margin-top: 2rem; font-size: 0.85rem; color: #888;">Use your browser’s Print → Save as PDF to save this stub as PDF.</p>
</body>
</html>
