<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
initializeDatabase();

$message = isset($_GET['msg']) ? $_GET['msg'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    requireCsrfToken();
    $deleteId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($deleteId) {
        $apiKey = getApiKeyForAdmin();
        if (!$apiKey) {
            $error = 'No API key. Create one in API Keys first.';
        } else {
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $baseUrl . '/api/delete-employee.php?id=' . $deleteId,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_HTTPHEADER => ['X-API-Key: ' . $apiKey],
                CURLOPT_RETURNTRANSFER => true,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $result = json_decode($resp, true);
            if ($code >= 200 && $code < 300 && !empty($result['success'])) {
                header('Location: employees.php?msg=' . urlencode('Employee deleted.'));
                exit;
            }
            header('Location: employees.php?error=' . urlencode($result['error'] ?? 'Delete failed (HTTP ' . $code . ')'));
            exit;
        }
    }
}

$db = getDbConnection();
$r = $db->query("SELECT e.id, e.full_name, e.ssn, e.filing_status, e.hire_date, e.monthly_gross_salary, (SELECT COUNT(*) FROM payroll_history WHERE employee_id = e.id) as payroll_count FROM employees e ORDER BY e.full_name");
$employees = [];
while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
    $row['ssn'] = maskSsn($row['ssn']);
    $row['payroll_count'] = (int)($row['payroll_count'] ?? 0);
    $employees[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees — Payroll</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="site-title">Payroll</h1>
            <p class="site-subtitle">Employees</p>
        </div>
    </header>
    <div class="container">
        <main class="main-content">
            <div class="flex" style="justify-content: space-between; margin-bottom: 2rem;">
                <h1>Employees</h1>
                <div class="flex">
                    <a href="employee-form.php" class="btn">Add employee</a>
                    <a href="index.php" class="btn btn-secondary">Back</a>
                </div>
            </div>
            <?php if ($message): ?><div class="info-box"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="info-box" style="color: var(--danger);"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if (empty($employees)): ?>
                <div class="info-box">No employees yet. <a href="employee-form.php">Add one</a>.</div>
            <?php else: ?>
                <div class="info-box">
                    <table>
                        <thead><tr><th>Name</th><th>SSN</th><th>Filing status</th><th>Hire date</th><th>Monthly gross</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($employees as $e): ?>
                                <tr>
                                    <td><?= htmlspecialchars($e['full_name']) ?></td>
                                    <td><?= htmlspecialchars($e['ssn']) ?></td>
                                    <td><?= htmlspecialchars($e['filing_status']) ?></td>
                                    <td><?= htmlspecialchars($e['hire_date']) ?></td>
                                    <td>$<?= number_format((float)$e['monthly_gross_salary'], 2) ?></td>
                                    <td class="flex" style="gap: 0.5rem;">
                                        <a href="employee-form.php?id=<?= (int)$e['id'] ?>" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">Edit</a>
                                        <?php if ($e['payroll_count'] === 0): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this employee?');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                                                <button type="submit" class="btn danger" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.8rem;">(has payroll)</span>
                                        <?php endif; ?>
                                    </td>
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
