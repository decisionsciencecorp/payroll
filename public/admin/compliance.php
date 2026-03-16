<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
initializeDatabase();

$db = getDbConnection();
$r = $db->query("SELECT id, full_name, hire_date, i9_completed_at, w4_file_path, w4_uploaded_at, i9_file_path, i9_uploaded_at FROM employees ORDER BY full_name");
$employees = [];
while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
    $employees[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compliance — Payroll</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="site-title">Payroll</h1>
            <p class="site-subtitle">Forms (W-4 / I-9)</p>
        </div>
    </header>
    <div class="container">
        <main class="main-content">
            <div class="flex" style="justify-content: space-between; margin-bottom: 2rem;">
                <h1>Compliance</h1>
                <a href="index.php" class="btn btn-secondary">Back</a>
            </div>
            <p class="mb-2">W-4 and I-9 document status. Upload from each employee’s edit page.</p>
            <?php if (empty($employees)): ?>
                <div class="info-box">No employees. <a href="employee-form.php">Add one</a>.</div>
            <?php else: ?>
                <div class="info-box">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Hire date</th>
                                <th>I-9 completed date</th>
                                <th>W-4 on file</th>
                                <th>I-9 on file</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $e): ?>
                                <tr>
                                    <td><?= htmlspecialchars($e['full_name']) ?></td>
                                    <td><?= htmlspecialchars($e['hire_date'] ?? '') ?></td>
                                    <td><?= !empty($e['i9_completed_at']) ? htmlspecialchars($e['i9_completed_at']) : '—' ?></td>
                                    <td><?= !empty($e['w4_file_path']) ? (!empty($e['w4_uploaded_at']) ? date('M j, Y', strtotime($e['w4_uploaded_at'])) : 'Yes') : '—' ?></td>
                                    <td><?= !empty($e['i9_file_path']) ? (!empty($e['i9_uploaded_at']) ? date('M j, Y', strtotime($e['i9_uploaded_at'])) : 'Yes') : '—' ?></td>
                                    <td class="flex" style="gap: 0.5rem;">
                                        <?php if (!empty($e['w4_file_path'])): ?>
                                            <a href="employee-document.php?employee_id=<?= (int)$e['id'] ?>&doc=w4" target="_blank" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">W-4</a>
                                        <?php endif; ?>
                                        <?php if (!empty($e['i9_file_path'])): ?>
                                            <a href="employee-document.php?employee_id=<?= (int)$e['id'] ?>&doc=i9" target="_blank" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">I-9</a>
                                        <?php endif; ?>
                                        <a href="employee-form.php?id=<?= (int)$e['id'] ?>" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">Edit</a>
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
