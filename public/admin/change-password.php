<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAuth();

$currentUser = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($new !== $confirm) {
        $error = 'New password and confirmation do not match';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters';
    } else {
        $result = changePassword($currentUser['id'], $current, $new);
        if ($result['success']) $success = 'Password updated.';
        else $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change password — Payroll</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="site-title">Payroll</h1>
            <p class="site-subtitle">Change password</p>
        </div>
    </header>
    <div class="container">
        <main class="main-content" style="max-width: 500px;">
            <div class="mb-2"><a href="index.php" class="btn btn-secondary">← Back</a></div>
            <?php if ($error): ?><div class="info-box" style="color: var(--danger);"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="info-box"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <form method="POST" class="info-box">
                <?= csrfField() ?>
                <h2 style="margin-bottom: 1rem;">Update password</h2>
                <div class="mb-2">
                    <label for="current_password">Current password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="mb-2">
                    <label for="new_password">New password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8">
                </div>
                <div class="mb-2">
                    <label for="confirm_password">Confirm new password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="flex">
                    <button type="submit" class="btn">Update</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </main>
    </div>
</body>
</html>
