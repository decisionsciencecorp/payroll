<?php
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function getCsrfToken() {
    return generateCsrfToken();
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            http_response_code(403);
            die('CSRF token validation failed. Please refresh and try again.');
        }
    }
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken()) . '">';
}
