<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
initializeDatabase();

$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$doc = isset($_GET['doc']) ? strtolower(trim($_GET['doc'])) : '';
if ($employeeId < 1 || !in_array($doc, ['w4', 'i9'], true)) {
    http_response_code(400);
    exit;
}

$db = getDbConnection();
$col = $doc === 'w4' ? 'w4_file_path' : 'i9_file_path';
$stmt = $db->prepare("SELECT id, $col as file_path FROM employees WHERE id = :id");
$stmt->bindValue(':id', $employeeId, SQLITE3_INTEGER);
$r = $stmt->execute();
$row = $r->fetchArray(SQLITE3_ASSOC);
if (!$row || empty($row['file_path'])) {
    http_response_code(404);
    exit;
}
$filename = $row['file_path'];
$baseDir = STORAGE_PATH . '/employees/' . $employeeId;
$fullPath = $baseDir . '/' . $filename;
if (!is_file($fullPath)) {
    http_response_code(404);
    exit;
}
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimes = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
$mime = $mimes[$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($filename) . '"');
header('Cache-Control: private, max-age=3600');
readfile($fullPath);
