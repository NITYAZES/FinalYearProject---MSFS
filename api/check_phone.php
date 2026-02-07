<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Read raw body
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        echo json_encode(['available' => false]);
        exit;
    }

    $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $phone = trim((string)($input['phone'] ?? ''));

    if ($phone === '') {
        echo json_encode(['available' => false]);
        exit;
    }

    $pdo = db();

    // Check if phone exists
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE user_phone = ? LIMIT 1');
    $stmt->execute([$phone]);

    $exists = $stmt->fetchColumn() !== false;

    echo json_encode([
        'available' => !$exists,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // Safe default on any error
    echo json_encode([
        'available' => false,
        'error'     => 'server_error',
    ], JSON_UNESCAPED_UNICODE);
}
