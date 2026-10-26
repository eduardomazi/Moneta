<?php
declare(strict_types=1);

$isHttps = !empty($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== 'off';

session_name('moneta_session');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function readJsonBody(): array
{
    $data = json_decode(
        file_get_contents('php://input') ?: '',
        true
    );

    return is_array($data) ? $data : [];
}

function requireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        jsonResponse(['error' => 'Método não permitido.'], 405);
    }
}

function requireCsrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';

    if (
        !is_string($provided) ||
        $expected === '' ||
        !hash_equals($expected, $provided)
    ) {
        jsonResponse([
            'error' => 'Token de segurança inválido. Atualize a página.'
        ], 403);
    }
}

function requireAuth(): int
{
    $id = $_SESSION['user_id'] ?? null;

    if (!is_int($id) && !ctype_digit((string)$id)) {
        jsonResponse([
            'error' => 'Faça login para continuar.'
        ], 401);
    }

    return (int)$id;
}