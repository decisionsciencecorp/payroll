<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAuth();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($username) < 2) {
            $message = 'Username too short';
            $messageType = 'error';
        } elseif ($password !== $confirm) {
            $message = 'Passwords do not match';
            $messageType = 'error';
        } elseif (strlen($password) < 8) {
            $message = 'Password must be at least 8 characters';
            $messageType = 'error';
        } else {
            $result = addAdminUser($username, $password);
            if ($result['success']) {
                $message = 'User added.';
                $messageType = 'success';
            } else {
                $message = $result['error'];
                $messageType = 'error';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
        $result = deleteAdminUser((int)$_POST['id']);
        if ($result['success']) {
            $message = 'User deleted.';
            $messageType = 'success';
        } else {
            $message = $result['error'];
            $messageType = 'error';
        }
    }
}

$users = getAllAdminUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin users — Payroll</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="site-title">Payroll</h1>
            <p class="site-subtitle">Admin users</p>
        </div>
    </header>
    <div class="container">
        <main class="main-content">
            <div class="flex" style="justify-content: space-between; margin-bottom: 2rem;">
                <h1>Admin users</h1>
                <a href="index.php" class="btn btn-secondary">Back</a>
            </div>
            <?php if ($message): ?>
                <div class="info-box" style="color: <?= $messageType === 'error' ? 'var(--danger)' : 'inherit' ?>;"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <div class="info-box mb-2">
                <h2 style="margin-bottom: 1rem;">Add user</h2>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="flex" style="gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                        <div style="min-width: 120px;">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required minlength="2">
                        </div>
                        <div style="min-width: 140px;">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required minlength="8">
                        </div>
                        <div style="min-width: 140px;">
                            <label for="confirm_password">Confirm</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn">Add user</button>
                    </div>
                </form>
            </div>
            <h2 style="margin-bottom: 1rem;">Users</h2>
            <?php if (empty($users)): ?>
                <div class="info-box">No users.</div>
            <?php else: ?>
                <div class="info-box">
                    <table>
                        <thead><tr><th>Username</th><th>Created</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?= htmlspecialchars($u['username']) ?></td>
                                    <td><?= formatDate($u['created_at']) ?></td>
                                    <td>
                                        <?php if (count($users) > 1): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                            <button type="submit" class="btn btn-secondary danger">Delete</button>
                                        </form>
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
