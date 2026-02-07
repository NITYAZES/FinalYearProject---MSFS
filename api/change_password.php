<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

session_start();

function json_response(bool $success, string $message, int $code = 200): void
{
  http_response_code($code);
  echo json_encode(
    ['success' => $success, 'message' => $message],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
  );
  exit;
}

/* -------------------- Password Policy -------------------- */
function password_policy_ok(string $pwd, string $name, string $username, string $email): array
{
  if (strlen($pwd) < 12) return [false, 'Password must be at least 12 characters'];
  if (!preg_match('/[a-z]/', $pwd)) return [false, 'Password must include a lowercase letter'];
  if (!preg_match('/[A-Z]/', $pwd)) return [false, 'Password must include an uppercase letter'];
  if (!preg_match('/[0-9]/', $pwd)) return [false, 'Password must include a number'];
  if (!preg_match('/[^A-Za-z0-9]/', $pwd)) return [false, 'Password must include a special character'];

  $common = [
    'password','password123','password@123','qwerty','qwerty123',
    'admin','admin123','welcome','letmein','iloveyou',
    '123456','12345678','123456789','1234567890',
    'abc123','000000','111111','passw0rd','p@ssw0rd'
  ];
  if (in_array(strtolower(trim($pwd)), $common, true)) return [false, 'Password is too common'];

  $pwdLower = strtolower(trim($pwd));
  $nameLower = strtolower(trim($name));
  $userLower = strtolower(trim($username));
  $emailLower = strtolower(trim($email));
  $emailPrefix = explode('@', $emailLower)[0] ?? '';

  $tokens = array_values(array_filter(array_map('trim', array_merge(
    preg_split('/\s+/', $nameLower) ?: [],
    [$userLower, $emailPrefix]
  )), fn($t) => $t !== ''));

  foreach ($tokens as $t) {
    if (strlen($t) < 3) continue;

    if (str_contains($pwdLower, $t)) {
      return [false, 'Password cannot contain your name, username, or email'];
    }

    $max = strlen($t);
    for ($i = 3; $i <= $max; $i++) {
      $prefix = substr($t, 0, $i);
      if ($prefix !== '' && str_contains($pwdLower, $prefix)) {
        return [false, 'Password is too similar to your name, username, or email'];
      }
    }
  }

  return [true, 'ok'];
}

// Only POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(false, 'Invalid request method', 405);
}

// Check session
if (empty($_SESSION['user_id'])) {
  json_response(false, 'Unauthorized. Please login.', 401);
}

try {
  $raw = file_get_contents('php://input');
  if ($raw === false || $raw === '') json_response(false, 'No input', 400);

  if (strlen($raw) > 1_000_000) json_response(false, 'Payload too large', 413);

  $input = json_decode($raw, true);
  if (!is_array($input) || json_last_error() !== JSON_ERROR_NONE) {
    json_response(false, 'Invalid JSON', 400);
  }

  $userId = (int)($_SESSION['user_id']);
  $requestedUserId = (int)($input['user_id'] ?? 0);
  $currentPassword = (string)($input['current_password'] ?? '');
  $newPassword = (string)($input['new_password'] ?? '');

  if ($requestedUserId !== $userId) {
    json_response(false, 'Unauthorized access', 403);
  }

  if ($currentPassword === '' || $newPassword === '') {
    json_response(false, 'Current and new passwords are required', 400);
  }

  $pdo = db();
  $pdo->beginTransaction();

  $uStmt = $pdo->prepare('SELECT user_id, user_password, user_fullname, username, user_email FROM users WHERE user_id = ? LIMIT 1');
  $uStmt->execute([$userId]);
  $user = $uStmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    $pdo->rollBack();
    json_response(false, 'User not found', 404);
  }

  if (!password_verify($currentPassword, (string)$user['user_password'])) {
    $pdo->rollBack();
    json_response(false, 'Current password is incorrect', 401);
  }

  [$ok, $msg] = password_policy_ok(
    $newPassword,
    (string)($user['user_fullname'] ?? ''),
    (string)($user['username'] ?? ''),
    (string)($user['user_email'] ?? '')
  );
  if (!$ok) {
    $pdo->rollBack();
    json_response(false, $msg, 422);
  }

  if (password_verify($newPassword, (string)$user['user_password'])) {
    $pdo->rollBack();
    json_response(false, 'New password must be different from current password', 422);
  }

  // Decode crypto parameters
  $kekEncNew = isset($input['kek_enc']) ? base64_decode((string)$input['kek_enc'], true) : false;
  $kekIvNew = isset($input['kek_iv']) ? base64_decode((string)$input['kek_iv'], true) : false;
  $pwkdfSaltNew = isset($input['pwkdf_salt']) ? base64_decode((string)$input['pwkdf_salt'], true) : false;
  $pwkdfIterationsNew = (int)($input['pwkdf_iterations'] ?? 0);

  if ($kekEncNew === false || $kekIvNew === false || $pwkdfSaltNew === false || $pwkdfIterationsNew < 100000) {
    $pdo->rollBack();
    json_response(false, 'Invalid encryption parameters', 400);
  }

  if (strlen($kekIvNew) !== 12) {
    $pdo->rollBack();
    json_response(false, 'Invalid IV length (must be 12 bytes)', 400);
  }

  if (strlen($pwkdfSaltNew) !== 16) {
    $pdo->rollBack();
    json_response(false, 'Invalid salt length (must be 16 bytes)', 400);
  }

  if (strlen($kekEncNew) > 512) {
    $pdo->rollBack();
    json_response(false, 'kek_enc too large', 400);
  }

  $newPasswordHash = defined('PASSWORD_ARGON2ID')
    ? password_hash($newPassword, PASSWORD_ARGON2ID, ['memory_cost' => 1 << 17, 'time_cost' => 4, 'threads' => 2])
    : password_hash($newPassword, PASSWORD_DEFAULT);

  if ($newPasswordHash === false) {
    $pdo->rollBack();
    json_response(false, 'Failed to hash password', 500);
  }

  $updateUserStmt = $pdo->prepare('UPDATE users SET user_password = ?, updated_at = NOW() WHERE user_id = ?');
  if (!$updateUserStmt->execute([$newPasswordHash, $userId])) {
    $pdo->rollBack();
    json_response(false, 'Failed to update password', 500);
  }

  $kekStmt = $pdo->prepare('SELECT kek_version FROM user_kek WHERE user_id = ? AND is_active = 1 LIMIT 1');
  $kekStmt->execute([$userId]);
  $kekRow = $kekStmt->fetch(PDO::FETCH_ASSOC);
  $currentVersion = (int)($kekRow['kek_version'] ?? 0);

  $pdo->prepare('UPDATE user_kek SET is_active = 0, rotated_at = NOW() WHERE user_id = ?')->execute([$userId]);

  $insertKekStmt = $pdo->prepare(
    'INSERT INTO user_kek (user_id, kek_enc, kek_iv, pwkdf_salt, pwkdf_iterations, kek_version, is_active)
     VALUES (?, ?, ?, ?, ?, ?, 1)'
  );

  if (!$insertKekStmt->execute([$userId, $kekEncNew, $kekIvNew, $pwkdfSaltNew, $pwkdfIterationsNew, $currentVersion + 1])) {
    $pdo->rollBack();
    json_response(false, 'Failed to update encryption keys', 500);
  }

  $pdo->commit();

  // Audit log (optional) - matches your schema
  try {
    $logStmt = $pdo->prepare('
      INSERT INTO security_audit_log
        (user_id, event_type, event_category, severity, description, user_agent, metadata_json, created_at)
      VALUES
        (:user_id, :event_type, :category, :severity, :description, :ua, :meta, NOW())
    ');

    $logStmt->execute([
      ':user_id' => $userId,
      ':event_type' => 'password_changed',
      ':category' => 'security',
      ':severity' => 'info',
      ':description' => 'User changed password and rotated KEK',
      ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 512),
      ':meta' => json_encode([
        'kek_rotated' => true,
        'kek_version_new' => $currentVersion + 1,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'timestamp' => date('Y-m-d H:i:s'),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
  } catch (Throwable $e) {
    error_log('Failed to log password change: ' . $e->getMessage());
  }

  json_response(true, 'Password changed successfully', 200);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  error_log('Change password error: ' . $e->getMessage());
  json_response(false, 'Server error', 500);
}
