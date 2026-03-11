<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
initializeDatabase();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['logo'])) {
    requireCsrfToken();
    if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['logo']['tmp_name']);
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg'];
        if (isset($allowed[$mime]) && $_FILES['logo']['size'] <= 2 * 1024 * 1024) {
            $ext = $allowed[$mime];
            $dir = STORAGE_PATH;
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $path = $dir . '/logo.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $path)) {
                $db = getDbConnection();
                $db->prepare("UPDATE company_settings SET logo_path = :p, updated_at = CURRENT_TIMESTAMP WHERE id = 1")->bindValue(':p', 'logo.' . $ext, SQLITE3_TEXT)->execute();
                $message = 'Logo updated.';
            } else {
                $message = 'Failed to save file.';
            }
        } else {
            $message = 'Only PNG/JPEG, max 2MB.';
        }
    }
}

$db = getDbConnection();
$logoPath = $db->querySingle("SELECT logo_path FROM company_settings WHERE id = 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logo — Payroll</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="site-title">Payroll</h1>
            <p class="site-subtitle">Company logo</p>
        </div>
    </header>
    <div class="container">
        <main class="main-content">
            <div class="flex" style="justify-content: space-between; margin-bottom: 2rem;">
                <h1>Logo</h1>
                <a href="index.php" class="btn btn-secondary">Back</a>
            </div>
            <?php if ($message): ?><div class="info-box"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <div class="info-box">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <label for="logo">Upload logo (PNG or JPEG, max 2MB)</label>
                    <input type="file" id="logo" name="logo" accept="image/png,image/jpeg" style="margin: 0.5rem 0;">
                    <button type="submit" class="btn">Upload</button>
                </form>
            </div>
            <?php if ($logoPath): ?>
                <p>Current logo:</p>
                <img src="/api/logo-file.php" alt="Logo" style="max-width: 200px; max-height: 100px;">
            <?php else: ?>
                <p>No logo set.</p>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
