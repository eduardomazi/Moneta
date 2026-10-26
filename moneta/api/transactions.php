<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$userId = requireAuth();

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT id, description, amount, type, category,
                DATE_FORMAT(transaction_date, '%Y-%m-%d') AS date
         FROM transactions
         WHERE user_id = ?
         ORDER BY transaction_date DESC, id DESC"
    );

    $stmt->execute([$userId]);

    jsonResponse([
        'transactions' => $stmt->fetchAll()
    ]);
}

if ($method === 'POST') {
    requireCsrf();

    $data = readJsonBody();

    $description = trim(
        is_string($data['description'] ?? null)
            ? $data['description']
            : ''
    );

    $amountRaw = $data['amount'] ?? null;
    $amount = is_numeric($amountRaw) ? (float)$amountRaw : 0;

    $type = $data['type'] ?? '';

    $category = trim(
        is_string($data['category'] ?? null)
            ? $data['category']
            : ''
    );

    $date = $data['date'] ?? '';

    $dateObject = is_string($date)
        ? DateTime::createFromFormat('!Y-m-d', $date)
        : false;

    $validDate = $dateObject !== false
        && $dateObject->format('Y-m-d') === $date;

    if (
        $description === '' ||
        strlen($description) > 150 ||
        !is_finite($amount) ||
        $amount <= 0 ||
        $amount > 9999999999 ||
        !in_array($type, ['income', 'expense'], true) ||
        $category === '' ||
        strlen($category) > 60 ||
        !$validDate
    ) {
        jsonResponse([
            'error' => 'Confira os dados da transação.'
        ], 400);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO transactions
         (user_id, description, amount, type, category, transaction_date)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $userId,
        $description,
        number_format($amount, 2, '.', ''),
        $type,
        $category,
        $date
    ]);

    jsonResponse([
        'message' => 'Transação salva!',
        'id' => (int)$pdo->lastInsertId()
    ], 201);
}

if ($method === 'DELETE') {
    requireCsrf();

    $id = filter_var(
        $_GET['id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$id || $id < 1) {
        jsonResponse([
            'error' => 'ID inválido.'
        ], 400);
    }

    $stmt = $pdo->prepare(
        'DELETE FROM transactions
         WHERE id = ? AND user_id = ?'
    );

    $stmt->execute([$id, $userId]);

    if ($stmt->rowCount() === 0) {
        jsonResponse([
            'error' => 'Transação não encontrada.'
        ], 404);
    }

    jsonResponse([
        'message' => 'Transação excluída.'
    ]);
}

header('Allow: GET, POST, DELETE');

jsonResponse([
    'error' => 'Método não permitido.'
], 405);