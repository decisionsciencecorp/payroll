<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
initializeDatabase();

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrfToken();
    if ($_POST['action'] === 'run') {
        $start = $_POST['pay_period_start'] ?? '';
        $end = $_POST['pay_period_end'] ?? '';
        $date = $_POST['pay_date'] ?? '';
        if ($start && $end && $date) {
            $ch = curl_init();
            $apiKey = getApiKeyForAdmin();
            if (!$apiKey) {
                $error = 'No API key. Create one in API Keys and set it in config for admin run.';
            } else {
                curl_setopt_array($ch, [
                    CURLOPT_URL => SITE_URL . '/api/run-payroll.php',
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode(['pay_period_start' => $start, 'pay_period_end' => $end, 'pay_date' => $date]),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-Key: ' . $apiKey],
                    CURLOPT_RETURNTRANSFER => true,
                ]);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $data = json_decode($resp, true);
                if ($code >= 200 && $code < 300 && !empty($data['success'])) {
                    $message = 'Payroll run completed. Records: ' . ($data['records'] ?? 0);
                } else {
                    $error = $data['error'] ?? 'Run failed (HTTP ' . $code . ')';
                }
            }
        } else {
            $error = 'Fill all three dates.';
        }
    }
}

$db = getDbConnection();
$r = $db->query("SELECT p.*, e.full_name FROM payroll_history p JOIN employees e ON e.id = p.employee_id ORDER BY p.pay_date DESC, p.id DESC LIMIT 100");
$payrolls = [];
while ($row = $r->fetchArray(SQLITE3_ASSOC)) $payrolls[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll — Payroll</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="site-title">Payroll</h1>
            <p class="site-subtitle">Payroll runs</p>
        </div>
    </header>
    <div class="container">
        <main class="main-content">
            <div class="flex" style="justify-content: space-between; margin-bottom: 2rem;">
                <h1>Payroll</h1>
                <a href="index.php" class="btn btn-secondary">Back</a>
            </div>
            <?php if ($message): ?><div class="info-box"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="info-box" style="color: var(--danger);"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div class="info-box mb-2">
                <h2 style="margin-bottom: 1rem;">Run payroll</h2>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="run">
                    <div class="flex" style="align-items: flex-end;">
                        <div><label>Period start</label><input type="date" name="pay_period_start" required></div>
                        <div><label>Period end</label><input type="date" name="pay_period_end" required></div>
                        <div><label>Pay date</label><input type="date" name="pay_date" required></div>
                        <button type="submit" class="btn">Run</button>
                    </div>
                </form>
            </div>
            <h2 style="margin-bottom: 1rem;">Recent payroll</h2>
            <?php if (empty($payrolls)): ?>
                <div class="info-box">No payroll runs yet.</div>
            <?php else: ?>
                <div class="info-box">
                    <table>
                        <thead><tr><th>Date</th><th>Employee</th><th>Gross</th><th>Net</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($payrolls as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['pay_date']) ?></td>
                                    <td><?= htmlspecialchars($p['full_name']) ?></td>
                                    <td>$<?= number_format((float)$p['gross_pay'], 2) ?></td>
                                    <td>$<?= number_format((float)$p['net_pay'], 2) ?></td>
                                    <td><a href="<?= htmlspecialchars(SITE_URL . '/api/pdf-stub.php?id=' . $p['id'] . '&api_key=' . urlencode(getApiKeyForAdmin() ?? '')) ?>" target="_blank" class="btn btn-secondary">Stub</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
