<?php

$host = 'localhost';
$dbname = 'moneta_db';
$username = 'moneta_app';
$password = 'Moneta_Dev_2026!';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log('Erro de conexão com o banco: ' . $e->getMessage());
    jsonResponse(['error' => 'Não foi possível conectar ao banco de dados.'], 500);
}