<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function out(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function bad(int $code, string $msg): void {
    out($code, ['ok' => false, 'message' => $msg]);
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        bad(405, 'Method not allowed');
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        bad(400, 'No input');
    }

    $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $email = trim((string)($input['email'] ?? ''));

    if ($email === '') {
        bad(422, 'Email is required');
    }

    $pdo = db();

    // Updated query to join with user_crypto_keys table
    $stmt = $pdo->prepare(
        'SELECT 
            u.user_id, 
            u.user_fullname, 
            u.user_email, 
            ck.public_key_jwk,
            u.email_verified_at
         FROM users u
         LEFT JOIN user_crypto_keys ck ON u.user_id = ck.user_id 
            AND ck.key_status = "active"
         WHERE LOWER(u.user_email) = LOWER(:email)
           AND u.status = "active"
         LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        bad(404, 'User not found for that email');
    }

    // Check if email is verified
    if (empty($user['email_verified_at'])) {
        bad(403, 'User email not verified');
    }

    if (empty($user['public_key_jwk'])) {
        bad(500, 'Receiver has no public key stored');
    }

    // public_key_jwk is stored as JSON string in DB
    $publicKeyJwk = json_decode($user['public_key_jwk'], true);
    if (!is_array($publicKeyJwk)) {
        bad(500, 'Invalid public key format on server');
    }

    out(200, [
        'ok'           => true,
        'publicKeyJwk' => $publicKeyJwk,
        'userId'       => (int)$user['user_id'],
        'userName'     => $user['user_fullname'],
    ]);

} catch (Throwable $e) {
    error_log('get-public-key error: ' . $e->getMessage());
    bad(500, 'Server error while loading public key');
}