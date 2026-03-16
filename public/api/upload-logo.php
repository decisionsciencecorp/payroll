<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
initializeDatabase();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
    exit;
}

$apiKey = getApiKey();
if (!$apiKey || !validateApiKey($apiKey)) {
    jsonError('Invalid or missing API key', 401);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit('logo:' . $apiKey . ':' . $ip, 10, 60)) {
    jsonError('Rate limit exceeded', 429);
    exit;
}

if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    jsonError('No file uploaded or upload error', 400);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($_FILES['logo']['tmp_name']);
$allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg'];
if (!isset($allowed[$mime])) {
    jsonError('Only PNG and JPEG allowed', 400);
    exit;
}
if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
    jsonError('Max size 2MB', 400);
    exit;
}

$ext = $allowed[$mime];
$storageDir = STORAGE_PATH;
if (!is_dir($storageDir)) {
    if (!@mkdir($storageDir, 0755, true)) {
        if (function_exists('app_log')) {
            app_log('error', 'Logo upload: could not create directory', ['path' => $storageDir]);
        }
        jsonError('Upload directory unavailable. Ensure public/uploads exists and is writable.', 503);
        exit;
    }
}
$filename = 'logo.' . $ext;
$path = $storageDir . '/' . $filename;
if (!move_uploaded_file($_FILES['logo']['tmp_name'], $path)) {
    if (function_exists('app_log')) {
        app_log('error', 'Logo upload: move_uploaded_file failed', ['path' => $path]);
    }
    jsonError('Failed to save file. Check upload directory permissions.', 500);
    exit;
}

$db = getDbConnection();
$stmt = $db->prepare("UPDATE company_settings SET logo_path = :path, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
$stmt->bindValue(':path', $filename, SQLITE3_TEXT);
$stmt->execute();

jsonSuccess(['message' => 'Logo updated']);
