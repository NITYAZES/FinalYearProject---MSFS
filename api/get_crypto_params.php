<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

session_start();

function json_response(bool $success, string $message, array $data = [], int $code = 200): void
{
  http_response_code($code);
  echo json_encode(array_merge(['success' => $success, 'message' => $message], $data), JSON_UNESCAPED_SLASHES);
  exit;
}

// Only POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(false, 'Invalid request method', [], 405);
}

// Check session
if (empty($_SESSION['user_id'])) {
  json_response(false, 'Unauthorized. Please login.', [], 401);
}

try {
  $raw = file_get_contents('php://input');
  $input = json_decode($raw, true);

  $requestedUserId = (int)($input['user_id'] ?? 0);
  $sessionUserId = (int)$_SESSION['user_id'];

  // Security: user can only get their own crypto params
  if ($requestedUserId !== $sessionUserId) {
    json_response(false, 'Unauthorized access', [], 403);
  }

  $pdo = db();

  // Fetch from user_kek table (updated schema)
  $stmt = $pdo->prepare(
    'SELECT kek_enc, kek_iv, pwkdf_salt, pwkdf_iterations
       FROM user_kek
      WHERE user_id = ? AND is_active = 1
      LIMIT 1'
  );
  $stmt->execute([$sessionUserId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    json_response(false, 'No encryption parameters found', [], 404);
  }

  // Check if crypto params exist
  if (empty($row['kek_enc']) || empty($row['kek_iv']) || empty($row['pwkdf_salt'])) {
    json_response(false, 'Incomplete encryption parameters', [], 400);
  }

  // Return base64-encoded values
  json_response(true, 'Crypto parameters retrieved', [
    'kek_enc' => base64_encode($row['kek_enc']),
    'kek_iv' => base64_encode($row['kek_iv']),
    'pwkdf_salt' => base64_encode($row['pwkdf_salt']),
    'pwkdf_iterations' => (int)$row['pwkdf_iterations'],
  ]);

} catch (Throwable $e) {
  error_log('get_crypto_params error: ' . $e->getMessage());
  json_response(false, 'Server error. Please try again later.', [], 500);
}