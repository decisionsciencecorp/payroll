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
            if (!is_dir($dir)) {
                if (!@mkdir($dir, 0755, true)) {
                    $message = 'Upload directory unavailable. Ensure public/uploads exists and is writable.';
                } else {
                    $path = $dir . '/logo.' . $ext;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $path)) {
                        $db = getDbConnection();
                        $stmt = $db->prepare("UPDATE company_settings SET logo_path = :p, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
                        $stmt->bindValue(':p', 'logo.' . $ext, SQLITE3_TEXT);
                        if ($stmt->execute()) {
                            $message = 'Logo updated.';
                        } else {
                            $message = 'File saved but database update failed. Try again.';
                        }
                    } else {
                        $message = 'Failed to save file. Check public/uploads permissions.';
                    }
                }
            } else {
                $path = $dir . '/logo.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $path)) {
                    $db = getDbConnection();
                    $stmt = $db->prepare("UPDATE company_settings SET logo_path = :p, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
                    $stmt->bindValue(':p', 'logo.' . $ext, SQLITE3_TEXT);
                    if ($stmt->execute()) {
                        $message = 'Logo updated.';
                    } else {
                        $message = 'File saved but database update failed. Try again.';
                    }
                } else {
                    $message = 'Failed to save file. Check public/uploads permissions.';
                }
            }
        } else {
            $message = 'Only PNG/JPEG, max 2MB.';
        }
    } else {
        $err = $_FILES['logo']['error'];
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server limit (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE => 'File too large.',
            UPLOAD_ERR_PARTIAL => 'Upload incomplete. Try again.',
            UPLOAD_ERR_NO_FILE => 'No file selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server misconfiguration: no temp directory.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write file.',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by server extension.',
        ];
        $message = $messages[$err] ?? 'Upload error (code ' . $err . ').';
    }
}

$db = getDbConnection();
$logoPath = $db->querySingle("SELECT logo_path FROM company_settings WHERE id = 1");
// If DB says no logo but file exists on disk (e.g. update failed earlier), repair and show it
if (!$logoPath && is_dir(STORAGE_PATH)) {
    foreach (['logo.png', 'logo.jpg'] as $candidate) {
        if (is_file(STORAGE_PATH . '/' . $candidate)) {
            $stmt = $db->prepare("UPDATE company_settings SET logo_path = :p, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
            $stmt->bindValue(':p', $candidate, SQLITE3_TEXT);
            if ($stmt->execute()) {
                $logoPath = $candidate;
            }
            break;
        }
    }
}
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
