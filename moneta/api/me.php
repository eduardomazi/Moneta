<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

requireMethod('GET');

$userId = requireAuth();

require_once __DIR__ . '/../config/database.php';

$stmt = $pdo->prepare(
    'SELECT id, name, email
     FROM users
     WHERE id = ?'
);

$stmt->execute([$userId]);

$user = $stmt->fetch();

if (!$user) {
    jsonResponse([
        'error' => 'Usuário não encontrado.'
    ], 401);
}

jsonResponse([
    'user' => [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email']
    ],
    'csrfToken' => $_SESSION['csrf_token']
]);