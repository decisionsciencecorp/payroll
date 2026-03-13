<?php
require_once __DIR__ . '/config.php';
initializeDatabase();

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function login($username, $password) {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT id, username, password_hash FROM admin_users WHERE username = :u");
    $stmt->bindValue(':u', $username, SQLITE3_TEXT);
    $r = $stmt->execute();
    $user = $r->fetchArray(SQLITE3_ASSOC);
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        return ['success' => true];
    }
    return ['success' => false, 'error' => 'Invalid username or password'];
}

function logout() {
    session_destroy();
    $_SESSION = [];
    return true;
}

function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) return null;
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT id, username, created_at FROM admin_users WHERE id = :id");
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $r = $stmt->execute();
    return $r->fetchArray(SQLITE3_ASSOC);
}

function changePassword($userId, $currentPassword, $newPassword) {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT password_hash FROM admin_users WHERE id = :id");
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $r = $stmt->execute();
    $user = $r->fetchArray(SQLITE3_ASSOC);
    if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Current password is incorrect'];
    }
    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
    $up = $db->prepare("UPDATE admin_users SET password_hash = :h WHERE id = :id");
    $up->bindValue(':h', $hash, SQLITE3_TEXT);
    $up->bindValue(':id', $userId, SQLITE3_INTEGER);
    $up->execute();
    return ['success' => true];
}

function addAdminUser($username, $password) {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT id FROM admin_users WHERE username = :u");
    $stmt->bindValue(':u', $username, SQLITE3_TEXT);
    $r = $stmt->execute();
    if ($r->fetchArray(SQLITE3_ASSOC)) {
        return ['success' => false, 'error' => 'Username already exists'];
    }
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
    $ins = $db->prepare("INSERT INTO admin_users (username, password_hash) VALUES (:u, :h)");
    $ins->bindValue(':u', $username, SQLITE3_TEXT);
    $ins->bindValue(':h', $hash, SQLITE3_TEXT);
    $ins->execute();
    return ['success' => true];
}

function getAllAdminUsers() {
    $db = getDbConnection();
    $r = $db->query("SELECT id, username, created_at FROM admin_users ORDER BY username");
    $out = [];
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) $out[] = $row;
    return $out;
}

function deleteAdminUser($id) {
    $db = getDbConnection();
    $r = $db->query("SELECT COUNT(*) as c FROM admin_users");
    $row = $r->fetchArray(SQLITE3_ASSOC);
    if ($row && (int)$row['c'] <= 1) {
        return ['success' => false, 'error' => 'Cannot delete the last admin'];
    }
    $stmt = $db->prepare("DELETE FROM admin_users WHERE id = :id");
    $stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
    $stmt->execute();
    return ['success' => true];
}
