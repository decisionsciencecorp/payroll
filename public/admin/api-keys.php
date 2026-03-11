<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
initializeDatabase();

$message = '';
$newKey = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $name = trim($_POST['key_name'] ?? '') ?: 'Unnamed';
        $newKey = createApiKey($name);
        $message = 'API key created. Copy it now — it will not be shown again.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
        deleteApiKey((int)$_POST['id']);
        $message = 'Key deleted.';
    }
}

$keys = getAllApiKeys();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Keys — Payroll</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="site-title">Payroll</h1>
            <p class="site-subtitle">API keys</p>
        </div>
    </header>
    <div class="container">
        <main class="main-content">
            <div class="flex" style="justify-content: space-between; margin-bottom: 2rem;">
                <h1>API Keys</h1>
                <a href="index.php" class="btn btn-secondary">Back</a>
            </div>
            <?php if ($message): ?>
                <div class="info-box mb-2">
                    <p><?= htmlspecialchars($message) ?></p>
                    <?php if ($newKey): ?>
                        <p style="margin-top: 1rem; word-break: break-all; font-family: monospace; background: var(--bg); padding: 0.75rem; border-radius: 6px;"><?= htmlspecialchars($newKey) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="info-box mb-2">
                <h2 style="margin-bottom: 1rem;">Create key</h2>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="flex" style="align-items: flex-end;">
                        <div style="min-width: 200px;">
                            <label for="key_name">Key name</label>
                            <input type="text" id="key_name" name="key_name" placeholder="e.g. Script">
                        </div>
                        <button type="submit" class="btn">Create</button>
                    </div>
                </form>
            </div>
            <h2 style="margin-bottom: 1rem;">Existing keys</h2>
            <?php if (empty($keys)): ?>
                <div class="info-box">No keys yet.</div>
            <?php else: ?>
                <div class="info-box">
                    <table>
                        <thead><tr><th>Name</th><th>Key</th><th>Created</th><th>Last used</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($keys as $k): ?>
                                <tr>
                                    <td><?= htmlspecialchars($k['key_name']) ?></td>
                                    <td style="font-family: monospace;"><?= substr($k['api_key'], 0, 16) ?>…</td>
                                    <td><?= formatDate($k['created_at']) ?></td>
                                    <td><?= $k['last_used'] ? formatDate($k['last_used']) : '—' ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this key?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                                            <button type="submit" class="btn btn-secondary danger">Delete</button>
                                        </form>
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
