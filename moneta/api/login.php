
<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

requireMethod('POST');
requireCsrf();

require_once __DIR__ . '/../config/database.php';

$data = readJsonBody();

$email = strtolower(trim(
    is_string($data['email'] ?? null) ? $data['email'] : ''
));

$password = is_string($data['password'] ?? null)
    ? $data['password']
    : '';

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 255 ||
    $password === '' ||
    strlen($password) > 72
) {
    jsonResponse([
        'error' => 'E-mail ou senha incorretos.'
    ], 401);
}

$stmt = $pdo->prepare(
    'SELECT id, name, email, password_hash
     FROM users
     WHERE email = ?
     LIMIT 1'
);

$stmt->execute([$email]);
$user = $stmt->fetch();

if (
    !$user ||
    !password_verify($password, $user['password_hash'])
) {
    jsonResponse([
        'error' => 'E-mail ou senha incorretos.'
    ], 401);
}

session_regenerate_id(true);

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

jsonResponse([
    'message' => 'Login realizado com sucesso!',
    'csrfToken' => $_SESSION['csrf_token'],
    'user' => [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email']
    ]
]);
