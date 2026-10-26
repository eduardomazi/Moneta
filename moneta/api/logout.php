<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

requireMethod('POST');
requireCsrf();
requireAuth();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax'
    ]);
}

session_destroy();

jsonResponse([
    'message' => 'Você saiu da conta.'
]);