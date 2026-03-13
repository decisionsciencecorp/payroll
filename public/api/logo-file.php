<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
initializeDatabase();

$apiKey = getApiKey();
$hasKey = $apiKey && validateApiKey($apiKey);
$hasSession = isLoggedIn();
if (!$hasKey && !$hasSession) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing or invalid API key, or admin session required']);
    exit;
}

$db = getDbConnection();
$logoPath = $db->querySingle("SELECT logo_path FROM company_settings WHERE id = 1");
$allowedLogoNames = ['logo.png', 'logo.jpg'];
if (!$logoPath || !in_array($logoPath, $allowedLogoNames, true)) {
    http_response_code(404);
    exit;
}
$fullPath = STORAGE_PATH . '/' . $logoPath;
if (!is_file($fullPath)) {
    http_response_code(404);
    exit;
}
$mime = pathinfo($fullPath, PATHINFO_EXTENSION) === 'png' ? 'image/png' : 'image/jpeg';
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
readfile($fullPath);
