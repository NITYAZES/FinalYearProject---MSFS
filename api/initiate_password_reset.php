<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../debug.log');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=utf-8');

function json_response(bool $success, string $message, array $data = [], int $code = 200): void
{
  http_response_code($code);
  echo json_encode(array_merge(['success' => $success, 'message' => $message], $data), JSON_UNESCAPED_SLASHES);
  exit;
}

function logSecurityEvent(PDO $pdo, ?int $userId, string $eventType, string $severity, string $description, array $meta = []): void
{
  try {
    // Always attach IP (your table has no ip column; store in metadata_json)
    $meta['ip'] = $meta['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    $stmt = $pdo->prepare('
      INSERT INTO security_audit_log
        (user_id, event_type, event_category, severity, description, user_agent, metadata_json, created_at)
      VALUES
        (:uid, :etype, "security", :sev, :descr, :ua, :meta, NOW())
    ');

    $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($metaJson === false) $metaJson = '{}';

    $stmt->execute([
      ':uid' => $userId,
      ':etype' => $eventType,
      ':sev' => $severity,
      ':descr' => $description,
      ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 512),
      ':meta' => $metaJson,
    ]);
  } catch (Throwable $e) {
    error_log('Password reset audit failed: ' . $e->getMessage());
  }
}

function ensureAttemptTable(PDO $pdo): void
{
  $pdo->exec('
    CREATE TABLE IF NOT EXISTS password_reset_attempts (
      id INT AUTO_INCREMENT PRIMARY KEY,
      ip_hash VARBINARY(32) NOT NULL,
      identity_hash VARBINARY(32) NOT NULL,
      fail_count INT NOT NULL DEFAULT 0,
      first_fail_at DATETIME NOT NULL,
      last_fail_at DATETIME NOT NULL,
      UNIQUE KEY uniq_ip_identity (ip_hash, identity_hash),
      INDEX idx_last_fail (last_fail_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ');
}

function recordFailureAttempt(PDO $pdo, string $ip, string $identity, int $windowSeconds = 900): int
{
  ensureAttemptTable($pdo);

  $ipHash = hash('sha256', $ip, true);
  $identityHash = hash('sha256', strtolower($identity), true);

  $sel = $pdo->prepare('SELECT id, fail_count, first_fail_at FROM password_reset_attempts WHERE ip_hash = ? AND identity_hash = ? LIMIT 1');
  $sel->execute([$ipHash, $identityHash]);
  $row = $sel->fetch(PDO::FETCH_ASSOC);

  $now = time();

  if (!$row) {
    $ins = $pdo->prepare('
      INSERT INTO password_reset_attempts (ip_hash, identity_hash, fail_count, first_fail_at, last_fail_at)
      VALUES (?, ?, 1, NOW(), NOW())
    ');
    $ins->execute([$ipHash, $identityHash]);
    return 1;
  }

  $first = strtotime((string)$row['first_fail_at']);
  if ($first === false || ($now - $first) > $windowSeconds) {
    $upd = $pdo->prepare('
      UPDATE password_reset_attempts
         SET fail_count = 1, first_fail_at = NOW(), last_fail_at = NOW()
       WHERE id = ?
    ');
    $upd->execute([(int)$row['id']]);
    return 1;
  }

  $newCount = ((int)$row['fail_count']) + 1;
  $upd = $pdo->prepare('
    UPDATE password_reset_attempts
       SET fail_count = ?, last_fail_at = NOW()
     WHERE id = ?
  ');
  $upd->execute([$newCount, (int)$row['id']]);
  return $newCount;
}

function clearFailureAttempts(PDO $pdo, string $ip, string $identity): void
{
  ensureAttemptTable($pdo);
  $ipHash = hash('sha256', $ip, true);
  $identityHash = hash('sha256', strtolower($identity), true);

  $del = $pdo->prepare('DELETE FROM password_reset_attempts WHERE ip_hash = ? AND identity_hash = ?');
  $del->execute([$ipHash, $identityHash]);
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
    'password', 'password123', 'password@123', 'qwerty', 'qwerty123',
    'admin', 'admin123', 'welcome', 'letmein', 'iloveyou',
    '123456', '12345678', '123456789', '1234567890',
    'abc123', '000000', '111111', 'passw0rd', 'p@ssw0rd'
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

    if (str_contains($pwdLower, $t)) return [false, 'Password cannot contain your name, username, or email'];

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

/* -------------------- Request method + CSRF -------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(false, 'Invalid request method', [], 405);
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!csrf_verify($csrfToken)) {
  try {
    $pdo = db();
    logSecurityEvent($pdo, null, 'password_reset_csrf_failed', 'warning', 'CSRF validation failed on initiate password reset', [
      'timestamp' => date('Y-m-d H:i:s'),
    ]);
  } catch (Throwable $e) {
    error_log('CSRF audit log failed: ' . $e->getMessage());
  }

  json_response(false, 'CSRF validation failed', [], 403);
}

try {
  $input = file_get_contents('php://input');
  if ($input === false || $input === '') json_response(false, 'No input', [], 400);
  if (strlen($input) > 1_000_000) json_response(false, 'Payload too large', [], 413);

  $data = json_decode($input, true);
  if (!$data || json_last_error() !== JSON_ERROR_NONE) json_response(false, 'Invalid request data', [], 400);

  $token = trim((string)($data['token'] ?? ''));
  $emailOrUsername = trim((string)($data['email_or_username'] ?? ''));

  $kekEncB64 = $data['kek_enc'] ?? null;
  $kekIvB64 = $data['kek_iv'] ?? null;
  $pwkdfSaltB64 = $data['pwkdf_salt'] ?? null;
  $pwkdfIters = (int)($data['pwkdf_iterations'] ?? 0);

  $newPassword = (string)($data['new_password'] ?? '');

  if ($token === '' || $emailOrUsername === '') json_response(false, 'Missing required fields', [], 400);
  if (!$kekEncB64 || !$kekIvB64 || !$pwkdfSaltB64 || $pwkdfIters < 100000 || $newPassword === '') {
    json_response(false, 'Missing or invalid crypto materials', [], 400);
  }

  $b64 = fn($s) => ($s && ($d = base64_decode((string)$s, true)) !== false) ? $d : null;

  $kekEnc = $b64($kekEncB64);
  $kekIv = $b64($kekIvB64);
  $pwkdfSalt = $b64($pwkdfSaltB64);

  if ($kekEnc === null) json_response(false, 'Invalid kek_enc b64', [], 400);
  if ($kekIv === null || strlen($kekIv) !== 12) json_response(false, 'kek_iv must be 12 bytes', [], 400);
  if ($pwkdfSalt === null || strlen($pwkdfSalt) !== 16) json_response(false, 'pwkdf_salt must be 16 bytes', [], 400);
  if (strlen($kekEnc) > 512) json_response(false, 'kek_enc too large', [], 400);

  $pdo = db();
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

  $token_hash = hash('sha256', $token, true);

  $pdo->beginTransaction();

  $stmt = $pdo->prepare(
    'SELECT pr.reset_id, pr.user_id, pr.reset_token_expires_at, pr.used_at
       FROM password_reset_requests pr
      WHERE pr.reset_token_hash = ?
        AND pr.used_at IS NULL
      ORDER BY pr.reset_id DESC
      LIMIT 1'
  );
  $stmt->execute([$token_hash]);
  $req = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$req) {
    $pdo->rollBack();

    $failCount = recordFailureAttempt($pdo, $ip, $emailOrUsername);

    logSecurityEvent($pdo, null, 'password_reset_invalid_token', 'warning', 'Invalid password reset token used (initiate step)', [
      'identity_hash' => hash('sha256', strtolower($emailOrUsername)),
      'fail_count_15m' => $failCount,
      'timestamp' => date('Y-m-d H:i:s'),
    ]);

    // Standardized signal
    logSecurityEvent($pdo, null, 'POSSIBLE_ENUMERATION', 'warning', 'Password reset attempt with invalid token', [
      'identity_hash' => hash('sha256', strtolower($emailOrUsername)),
      'fail_count_15m' => $failCount,
      'timestamp' => date('Y-m-d H:i:s'),
    ]);

    if ($failCount >= 5) {
      logSecurityEvent($pdo, null, 'password_reset_multiple_failures', 'warning', 'Multiple failed password reset attempts detected (initiate step)', [
        'identity_hash' => hash('sha256', strtolower($emailOrUsername)),
        'fail_count_15m' => $failCount,
        'timestamp' => date('Y-m-d H:i:s'),
      ]);
    }

    json_response(false, 'Invalid or expired reset link', [], 400);
  }

  $expiresAt = (string)($req['reset_token_expires_at'] ?? '');
  if ($expiresAt === '' || strtotime($expiresAt) <= time()) {
    $del = $pdo->prepare('DELETE FROM password_reset_requests WHERE reset_id = ?');
    $del->execute([(int)$req['reset_id']]);
    $pdo->commit();

    $failCount = recordFailureAttempt($pdo, $ip, $emailOrUsername);

    logSecurityEvent($pdo, (int)$req['user_id'], 'password_reset_expired_token', 'warning', 'Expired password reset token used (initiate step)', [
      'reset_id' => (int)$req['reset_id'],
      'expires_at' => $expiresAt,
      'identity_hash' => hash('sha256', strtolower($emailOrUsername)),
      'fail_count_15m' => $failCount,
      'timestamp' => date('Y-m-d H:i:s'),
    ]);

    json_response(false, 'Reset link has expired. Please request a new one', [], 400);
  }

  $userId = (int)$req['user_id'];
  $resetId = (int)$req['reset_id'];

  $userStmt = $pdo->prepare('SELECT user_id, user_fullname, user_email, username FROM users WHERE user_id = ? LIMIT 1');
  $userStmt->execute([$userId]);
  $user = $userStmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    $pdo->rollBack();

    logSecurityEvent($pdo, $userId, 'password_reset_user_not_found', 'warning', 'Password reset token matched but user record not found (initiate step)', [
      'reset_id' => $resetId,
      'timestamp' => date('Y-m-d H:i:s'),
    ]);

    logSecurityEvent($pdo, null, 'POSSIBLE_ENUMERATION', 'warning', 'Password reset attempt where token matched but user missing', [
      'reset_id' => $resetId,
      'timestamp' => date('Y-m-d H:i:s'),
    ]);

    json_response(false, 'User not found', [], 404);
  }

  if (
    strcasecmp((string)$user['user_email'], $emailOrUsername) !== 0 &&
    strcasecmp((string)$user['username'], $emailOrUsername) !== 0
  ) {
    $pdo->rollBack();

    $failCount = recordFailureAttempt($pdo, $ip, $emailOrUsername);

    logSecurityEvent($pdo, $userId, 'password_reset_identity_mismatch', 'warning', 'Password reset identity mismatch (initiate step)', [
      'reset_id' => $resetId,
      'provided_identity_hash' => hash('sha256', strtolower($emailOrUsername)),
      'fail_count_15m' => $failCount,
      'timestamp' => date('Y-m-d H:i:s'),
    ]);

    logSecurityEvent($pdo, $userId, 'POSSIBLE_ENUMERATION', 'warning', 'Password reset identity mismatch', [
      'reset_id' => $resetId,
      'provided_identity_hash' => hash('sha256', strtolower($emailOrUsername)),
      'fail_count_15m' => $failCount,
      'timestamp' => date('Y-m-d H:i:s'),
    ]);

    json_response(false, 'Email/username does not match reset request', [], 400);
  }

  [$pwdOk, $pwdMsg] = password_policy_ok(
    $newPassword,
    (string)($user['user_fullname'] ?? ''),
    (string)($user['username'] ?? ''),
    (string)($user['user_email'] ?? '')
  );

  if (!$pwdOk) {
    $pdo->rollBack();

    $failCount = recordFailureAttempt($pdo, $ip, $emailOrUsername);

    logSecurityEvent($pdo, $userId, 'password_reset_password_policy_violation', 'warning', 'Password reset blocked due to password policy violation (initiate step)', [
      'reset_id' => $resetId,
      'reason' => $pwdMsg,
      'fail_count_15m' => $failCount,
      'timestamp' => date('Y-m-d H:i:s'),
    ]);

    json_response(false, $pwdMsg, [], 422);
  }

  // Generate 6-digit OTP
  $otp = sprintf('%06d', random_int(0, 999999));

  // Hash OTP using SHA256 (binary) to match confirm_password_reset.php
  $otpHash = hash('sha256', $otp, true);

  // Hash new password
  if (defined('PASSWORD_ARGON2ID')) {
    $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID, [
      'memory_cost' => 1 << 17,
      'time_cost' => 4,
      'threads' => 2,
    ]);
  } else {
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
  }
  if ($passwordHash === false) {
    $pdo->rollBack();
    json_response(false, 'Failed to hash password', [], 500);
  }

  $expiresIn15Min = date('Y-m-d H:i:s', time() + 900);

  $pendingStmt = $pdo->prepare(
    'INSERT INTO password_reset_pending
        (reset_request_id, user_id, confirmation_token_hash,
         new_kek_enc, new_kek_iv, new_pwkdf_salt, new_pwkdf_iterations,
         new_password_hash, expires_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );

  $ok = $pendingStmt->execute([
    $resetId,
    $userId,
    $otpHash,
    $kekEnc,
    $kekIv,
    $pwkdfSalt,
    $pwkdfIters,
    $passwordHash,
    $expiresIn15Min
  ]);

  if (!$ok) {
    $pdo->rollBack();
    json_response(false, 'Failed to initiate password reset', [], 500);
  }

  $pdo->commit();

  clearFailureAttempts($pdo, $ip, $emailOrUsername);

  logSecurityEvent($pdo, $userId, 'password_reset_initiated_otp_sent', 'info', 'Password reset initiated (OTP generated and pending reset stored)', [
    'reset_id' => $resetId,
    'identity_hash' => hash('sha256', strtolower($emailOrUsername)),
    'pending_expires_at' => $expiresIn15Min,
    'token_expires_at' => $expiresAt,
    'timestamp' => date('Y-m-d H:i:s'),
  ]);

  // Send OTP email (unchanged; do NOT log OTP)
  $userEmail = (string)$user['user_email'];
  $userName = (string)($user['user_fullname'] ?? $user['username'] ?? 'User');

  try {
    /** @var PHPMailer\PHPMailer\PHPMailer $mail */
    $mail = require __DIR__ . '/mailer.php';

    $mail->clearAllRecipients();
    $mail->setFrom('noreply@securefileshare.com', 'Secure File Share');
    $mail->addAddress($userEmail, $userName);
    $mail->Subject = 'Confirm Your Password Reset - OTP Required';

    $mail->Body = "
    <html><body style='font-family: Arial, sans-serif; color:#333;'>
      <div style='max-width:600px;margin:0 auto;padding:20px;'>
        <h2>🔐 Confirm Your Password Reset</h2>
        <p>Hello <strong>{$userName}</strong>,</p>
        <p>Your confirmation code is:</p>
        <div style='background:#f4f4f4;padding:18px;text-align:center;border-radius:8px;margin:20px 0;'>
          <div style='font-size:32px;font-weight:700;color:#2563eb;letter-spacing:5px;'>{$otp}</div>
        </div>
        <p><strong>This code expires in 15 minutes.</strong></p>
        <p style='font-size:12px;color:#666;'>Never share your recovery key or OTP codes with anyone.</p>
      </div>
    </body></html>";

    $mail->AltBody = "Your confirmation code is: {$otp}\nIt expires in 15 minutes.";
    $mail->send();
  } catch (Throwable $e) {
    error_log("OTP email send failed: " . $e->getMessage());
    logSecurityEvent($pdo, $userId, 'password_reset_otp_email_failed', 'warning', 'OTP email failed to send during password reset initiation', [
      'reset_id' => $resetId,
      'error' => $e->getMessage(),
      'timestamp' => date('Y-m-d H:i:s'),
    ]);
  }

  json_response(true, 'Confirmation code sent to your email. Please check your inbox.', [
    'email_hint' => substr($userEmail, 0, 3) . '***@' . explode('@', $userEmail)[1],
    'expires_in_minutes' => 15
  ], 200);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
  error_log('initiate_password_reset error: ' . $e->getMessage());

  try {
    $pdo2 = isset($pdo) && $pdo instanceof PDO ? $pdo : db();
    logSecurityEvent($pdo2, null, 'password_reset_server_error', 'error', 'Server error during password reset initiation', [
      'timestamp' => date('Y-m-d H:i:s'),
    ]);
  } catch (Throwable $e2) {
    error_log('Server error audit failed: ' . $e2->getMessage());
  }

  json_response(false, 'Server error. Please try again later.', [], 500);
}