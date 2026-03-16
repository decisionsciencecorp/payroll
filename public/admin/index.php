<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
initializeDatabase();

$db = getDbConnection();
$empCount = $db->querySingle("SELECT COUNT(*) FROM employees");
$lastPay = $db->querySingle("SELECT pay_date FROM payroll_history ORDER BY pay_date DESC LIMIT 1");
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Payroll</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="site-title">Payroll</h1>
            <p class="site-subtitle">Admin</p>
        </div>
    </header>
    <div class="container">
        <main class="main-content">
            <div class="flex" style="justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1>Dashboard</h1>
                <div class="flex">
                    <a href="employees.php" class="btn btn-secondary">Employees</a>
                    <a href="compliance.php" class="btn btn-secondary">Compliance</a>
                    <a href="payroll.php" class="btn btn-secondary">Payroll</a>
                    <a href="tax-config.php" class="btn btn-secondary">Tax config</a>
                    <a href="api-keys.php" class="btn btn-secondary">API Keys</a>
                    <a href="logo.php" class="btn btn-secondary">Logo</a>
                    <a href="company-settings.php" class="btn btn-secondary">Company</a>
                    <a href="w2.php" class="btn btn-secondary">W-2</a>
                    <a href="users.php" class="btn btn-secondary">Users</a>
                    <a href="change-password.php" class="btn btn-secondary">Change password</a>
                    <form method="POST" action="logout.php" style="display:inline;">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-secondary">Logout</button>
                    </form>
                </div>
            </div>
            <div class="info-box">
                <p>Employees: <strong><?= (int)$empCount ?></strong></p>
                <p>Last payroll date: <strong><?= $lastPay ? date('Y-m-d', strtotime($lastPay)) : '—' ?></strong></p>
            </div>
        </main>
    </div>
</body>
</html>
