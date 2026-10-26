<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

requireMethod('POST');
requireCsrf();

require_once __DIR__ . '/../config/database.php';

$data = readJsonBody();

$name = trim(
    is_string($data['name'] ?? null)
        ? $data['name']
        : ''
);

$email = strtolower(trim(
    is_string($data['email'] ?? null)
        ? $data['email']
        : ''
));

$password = is_string($data['password'] ?? null)
    ? $data['password']
    : '';

if (
    strlen($name) < 2 ||
    strlen($name) > 100 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 255 ||
    strlen($password) < 10 ||
    strlen($password) > 72
) {
    jsonResponse([
        'error' => 'Confira o nome, o e-mail e a senha.'
    ], 400);
}

try {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash)
         VALUES (?, ?, ?)'
    );

    $stmt->execute([$name, $email, $hash]);

    $userId = (int)$pdo->lastInsertId();

    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    jsonResponse([
        'message' => 'Conta criada com sucesso!',
        'csrfToken' => $_SESSION['csrf_token'],
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email
        ]
    ], 201);

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        jsonResponse([
            'error' => 'Este e-mail já está cadastrado.'
        ], 409);
    }

    error_log($e->getMessage());

    jsonResponse([
        'error' => 'Não foi possível criar a conta.'
    ], 500);
}