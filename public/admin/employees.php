<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
initializeDatabase();

$db = getDbConnection();
$r = $db->query("SELECT id, full_name, ssn, filing_status, hire_date, monthly_gross_salary FROM employees ORDER BY full_name");
$employees = [];
while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
    $row['ssn'] = maskSsn($row['ssn']);
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
                <a href="index.php" class="btn btn-secondary">Back</a>
            </div>
            <p style="color: var(--text-muted); margin-bottom: 1rem;">Add/edit via API. See docs/API-QUICK-REFERENCE.md.</p>
            <?php if (empty($employees)): ?>
                <div class="info-box">No employees yet.</div>
            <?php else: ?>
                <div class="info-box">
                    <table>
                        <thead><tr><th>Name</th><th>SSN</th><th>Filing status</th><th>Hire date</th><th>Monthly gross</th></tr></thead>
                        <tbody>
                            <?php foreach ($employees as $e): ?>
                                <tr>
                                    <td><?= htmlspecialchars($e['full_name']) ?></td>
                                    <td><?= htmlspecialchars($e['ssn']) ?></td>
                                    <td><?= htmlspecialchars($e['filing_status']) ?></td>
                                    <td><?= htmlspecialchars($e['hire_date']) ?></td>
                                    <td>$<?= number_format((float)$e['monthly_gross_salary'], 2) ?></td>
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
