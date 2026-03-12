<?php
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

$username = isset($_GET['username']) ? trim($_GET['username']) : '';

if ($username === '') {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $exists = (bool)$stmt->fetch();
    echo json_encode(['exists' => $exists]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['exists' => false]);
}
