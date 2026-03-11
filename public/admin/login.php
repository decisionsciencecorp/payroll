<?php
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $result = login($username, $password);
    if ($result['success']) {
        header('Location: index.php');
        exit;
    }
    $error = $result['error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Payroll</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="site-title">Payroll</h1>
            <p class="site-subtitle">Admin login</p>
        </div>
    </header>
    <div class="container">
        <main class="main-content" style="max-width: 400px; margin: 4rem auto;">
            <div class="info-box">
                <h2 style="margin-bottom: 1rem;">Login</h2>
                <?php if ($error): ?>
                    <p style="color: var(--danger); margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-2">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="mb-2">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn">Login</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
