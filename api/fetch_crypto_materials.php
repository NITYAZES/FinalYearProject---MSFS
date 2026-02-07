<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

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

try {
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);

  if (!$data || json_last_error() !== JSON_ERROR_NONE) {
    json_response(false, 'Invalid request data', [], 400);
  }

  $token = trim($data['token'] ?? '');

  if ($token === '') {
    json_response(false, 'Missing reset token', [], 400);
  }

  $pdo = db();
  $token_hash = hash('sha256', $token, true);

  // Look up the reset request
  $stmt = $pdo->prepare(
    'SELECT pr.reset_id, pr.user_id, pr.reset_token_expires_at, pr.used_at
       FROM password_reset_requests pr
      WHERE pr.reset_token_hash = ?
        AND pr.used_at IS NULL
      ORDER BY pr.reset_id DESC
      LIMIT 1'
  );

  $stmt->bindValue(1, $token_hash, PDO::PARAM_STR);
  $stmt->execute();
  $req = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$req) {
    json_response(false, 'Invalid or expired reset link', [], 400);
  }

  // Check if token has expired
  $expiresAt = (string)($req['reset_token_expires_at'] ?? '');
  if ($expiresAt === '' || strtotime($expiresAt) <= time()) {
    // Clean up expired token
    $del = $pdo->prepare('DELETE FROM password_reset_requests WHERE reset_id = ?');
    $del->execute([(int)$req['reset_id']]);
    json_response(false, 'Reset link has expired. Please request a new one', [], 400);
  }

  $userId = (int)$req['user_id'];

  // Fetch recovery key materials from user_recovery_keys table (updated schema)
  $userStmt = $pdo->prepare(
    'SELECT 
        kek_enc_rk,
        kek_rk_iv,
        rkdf_salt,
        rkdf_iterations
     FROM user_recovery_keys
     WHERE user_id = ? AND is_active = 1
     LIMIT 1'
  );

  $userStmt->execute([$userId]);
  $recovery = $userStmt->fetch(PDO::FETCH_ASSOC);

  if (!$recovery) {
    json_response(
      false,
      'Account does not support recovery key. Please contact support.',
      [],
      400
    );
  }

  // Check if recovery key materials exist
  if (
    empty($recovery['kek_enc_rk']) ||
    empty($recovery['kek_rk_iv']) ||
    empty($recovery['rkdf_salt'])
  ) {
    json_response(
      false,
      'Incomplete recovery key data. Please contact support.',
      [],
      400
    );
  }

  // Return recovery key materials (base64 encoded)
  json_response(
    true,
    'Crypto materials retrieved',
    [
      'kek_enc_rk' => base64_encode($recovery['kek_enc_rk']),
      'kek_rk_iv' => base64_encode($recovery['kek_rk_iv']),
      'rkdf_salt' => base64_encode($recovery['rkdf_salt']),
      'rkdf_iterations' => (int)($recovery['rkdf_iterations'] ?? 150000)
    ],
    200
  );

} catch (Throwable $e) {
  error_log('fetch_crypto_materials error: ' . $e->getMessage());
  json_response(false, 'Server error. Please try again later.', [], 500);
}