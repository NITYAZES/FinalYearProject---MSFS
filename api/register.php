<?php

declare(strict_types=1);

ob_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin_notification_helper.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/* -------------------- Response helpers -------------------- */
function out(int $code, array $payload): void
{
  if (ob_get_level()) {
    ob_end_clean();
  }

  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

/* -------------------- Request context helpers -------------------- */
function get_client_ip(): string
{
  // Prefer REMOTE_ADDR for trust (avoid spoofed X-Forwarded-For unless you have a trusted proxy setup)
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  return is_string($ip) ? $ip : '';
}

function get_user_agent(): string
{
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  return is_string($ua) ? substr($ua, 0, 512) : '';
}

/* -------------------- Security audit logger -------------------- */
function audit_log(
  ?PDO $pdo,
  ?int $userId,
  string $eventType,
  string $category,
  string $severity,
  string $description,
  array $metadata = []
): void {
  try {
    if (!$pdo) return;

    // Enrich metadata with request info (no secrets)
    $metadata = array_merge([
      'ip' => get_client_ip(),
    ], $metadata);

    $metaJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($metaJson === false) $metaJson = '{}';

    $sql = <<<SQL
INSERT INTO security_audit_log
  (user_id, event_type, event_category, severity, description, user_agent, metadata_json, created_at)
VALUES
  (:uid, :etype, :cat, :sev, :descr, :ua, :meta, NOW())
SQL;

    $st = $pdo->prepare($sql);
    $st->execute([
      ':uid' => $userId,
      ':etype' => (string)$eventType,
      ':cat' => (string)$category,
      ':sev' => (string)$severity,
      ':descr' => (string)$description,
      ':ua' => get_user_agent(),
      ':meta' => $metaJson,
    ]);
  } catch (Throwable $e) {
    // Never break main flow due to audit logging failure
    error_log('Audit log insert failed: ' . $e->getMessage());
  }
}

/* -------------------- Rate limit (simple IP-based) -------------------- */
/**
 * Basic anti-abuse limiter:
 * - Tracks attempts per IP in /tmp for a short window
 * - If exceeded -> block with 429 and log RATE_LIMIT_HIT
 */
function rate_limit_or_block(int $maxAttempts = 15, int $windowSeconds = 600): void
{
  $ip = get_client_ip();
  if ($ip === '') return;

  $key = 'reg_rl_' . hash('sha256', $ip);
  $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $key . '.json';

  $now = time();
  $data = ['start' => $now, 'count' => 0];

  if (is_file($file)) {
    $raw = @file_get_contents($file);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (is_array($decoded) && isset($decoded['start'], $decoded['count'])) {
      $data['start'] = (int)$decoded['start'];
      $data['count'] = (int)$decoded['count'];
    }
  }

  // Reset window if expired
  if (($now - $data['start']) > $windowSeconds) {
    $data = ['start' => $now, 'count' => 0];
  }

  $data['count']++;

  @file_put_contents($file, json_encode($data), LOCK_EX);

  if ($data['count'] > $maxAttempts) {
    // Best-effort audit log (open a new DB connection)
    try {
      $pdoTmp = db();
      audit_log(
        $pdoTmp,
        null,
        'RATE_LIMIT_HIT',
        'ABUSE',
        'HIGH',
        'Rate limit exceeded during registration attempts',
        [
          'window_seconds' => $windowSeconds,
          'max_attempts' => $maxAttempts,
          'attempts' => $data['count'],
        ]
      );
    } catch (Throwable $ignored) {
    }

    out(429, ['ok' => false, 'message' => 'Too many attempts. Please try again later.']);
  }
}

/* -------------------- Error helper with audit -------------------- */
function bad_with_audit(
  int $code,
  string $msg,
  ?PDO $pdo = null,
  ?int $userId = null,
  string $reason = 'unknown',
  array $meta = []
): void {
  error_log("ERROR ($code): $msg");

  // Map to REGISTER_REJECTED by default
  $category = 'AUTH';
  $severity = ($code >= 500) ? 'HIGH' : (($code >= 429) ? 'HIGH' : 'MEDIUM');

  // Categorize common abuse / enumeration
  $lower = strtolower($reason);
  if (str_contains($lower, 'payload') || $code === 413) $category = 'ABUSE';
  if (str_contains($lower, 'duplicate') || str_contains($lower, 'taken') || str_contains($lower, 'registered')) $category = 'ENUMERATION';

  audit_log(
    $pdo,
    $userId,
    'REGISTER_REJECTED',
    $category,
    $severity,
    $msg,
    array_merge(['reason' => $reason], $meta)
  );

  // Extra signal for enumeration-like rejects
  if ($category === 'ENUMERATION') {
    audit_log(
      $pdo,
      $userId,
      'POSSIBLE_ENUMERATION',
      'ENUMERATION',
      'MEDIUM',
      'Registration attempt revealed an existing identifier',
      array_merge(['reason' => $reason], $meta)
    );
  }

  out($code, ['ok' => false, 'message' => $msg]);
}

/* -------------------- Phone helpers -------------------- */
function normalize_phone_e164(string $raw): string
{
  $s = trim($raw);
  $s = preg_replace('/[\s\-\.\(\)]+/', '', $s) ?? '';
  if ($s === '') return $s;
  if (str_starts_with($s, '00')) $s = '+' . substr($s, 2);
  elseif ($s[0] === '0') $s = '+60' . ltrim($s, '0');
  elseif ($s[0] !== '+') $s = '+' . $s;
  return $s;
}

function is_valid_e164(string $p): bool
{
  return (bool)preg_match('/^\+[1-9]\d{6,14}$/', $p);
}

/* -------------------- Username + Password Policy -------------------- */
function valid_username(string $u): bool
{
  // 3-20 chars, only letters/numbers/dot/underscore/hyphen
  return (bool)preg_match('/^[A-Za-z0-9._-]{3,20}$/', $u);
}

function password_policy_ok(string $pwd, string $name, string $username, string $email): array
{
  if (strlen($pwd) < 12) return [false, 'Password must be at least 12 characters'];
  if (!preg_match('/[a-z]/', $pwd)) return [false, 'Password must include a lowercase letter'];
  if (!preg_match('/[A-Z]/', $pwd)) return [false, 'Password must include an uppercase letter'];
  if (!preg_match('/[0-9]/', $pwd)) return [false, 'Password must include a number'];
  if (!preg_match('/[^A-Za-z0-9]/', $pwd)) return [false, 'Password must include a special character'];

  $common = [
    'password',
    'password123',
    'password@123',
    'qwerty',
    'qwerty123',
    'admin',
    'admin123',
    'welcome',
    'letmein',
    'iloveyou',
    '123456',
    '12345678',
    '123456789',
    '1234567890',
    'abc123',
    '000000',
    '111111',
    'passw0rd',
    'p@ssw0rd'
  ];
  if (in_array(strtolower($pwd), $common, true)) return [false, 'Password is too common'];

  $pwdLower = strtolower($pwd);

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
      if (str_contains($pwdLower, $prefix)) {
        return [false, 'Password is too similar to your name, username, or email'];
      }
    }
  }

  return [true, 'ok'];
}

try {
  // Anti-abuse: rate limit first
  rate_limit_or_block(15, 600);

  $raw = file_get_contents('php://input');
  if ($raw === false || $raw === '') {
    bad_with_audit(400, 'No input', null, null, 'no_input');
  }

  // Request size limit (anti-DoS)
  if (strlen($raw) > 1_000_000) {
    // best-effort audit
    try {
      $pdoTmp = db();
      bad_with_audit(413, 'Payload too large', $pdoTmp, null, 'payload_too_large', [
        'bytes' => strlen($raw),
      ]);
    } catch (Throwable $e) {
      out(413, ['ok' => false, 'message' => 'Payload too large']);
    }
  }

  $in = json_decode($raw, true);
  if (!is_array($in)) {
    try {
      $pdoTmp = db();
      bad_with_audit(400, 'Invalid JSON', $pdoTmp, null, 'invalid_json');
    } catch (Throwable $e) {
      out(400, ['ok' => false, 'message' => 'Invalid JSON']);
    }
  }

  // Extract user fields
  $fullName = trim((string)($in['name'] ?? ''));
  $username = trim((string)($in['username'] ?? ''));
  $email = strtolower(trim((string)($in['email'] ?? '')));
  $phoneRaw = trim((string)($in['phone'] ?? ''));
  $password = (string)($in['password'] ?? '');

  // Create DB early so we can audit all rejects consistently
  $pdo = db();

  // Validate user fields
  if ($fullName === '') bad_with_audit(422, 'Full name is required', $pdo, null, 'fullname_required');
  if (strlen($fullName) > 150) bad_with_audit(422, 'Full name too long', $pdo, null, 'fullname_too_long');

  if ($username === '') bad_with_audit(422, 'Username is required', $pdo, null, 'username_required');
  if (!valid_username($username)) {
    bad_with_audit(422, 'Username must be 3-20 chars and only letters, numbers, dot, underscore, hyphen', $pdo, null, 'username_invalid', [
      'username' => $username
    ]);
  }

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) bad_with_audit(422, 'Valid email is required', $pdo, null, 'email_invalid');
  if (strlen($email) > 254) bad_with_audit(422, 'Email too long', $pdo, null, 'email_too_long');

  if ($phoneRaw === '') bad_with_audit(422, 'Phone number is required', $pdo, null, 'phone_required');

  if ($password === '') bad_with_audit(422, 'Password is required', $pdo, null, 'password_required');
  [$pwdOk, $pwdMsg] = password_policy_ok($password, $fullName, $username, $email);
  if (!$pwdOk) bad_with_audit(422, $pwdMsg, $pdo, null, 'password_policy_fail');

  // Format phone
  $phoneE164 = normalize_phone_e164($phoneRaw);
  if (!is_valid_e164($phoneE164)) bad_with_audit(422, 'Invalid phone. Use +60123456789 format', $pdo, null, 'phone_invalid');
  if (strlen($phoneE164) > 20) bad_with_audit(422, 'Phone too long (max 20 incl. +)', $pdo, null, 'phone_too_long');

  // Extract crypto payload
  $pubJwk = $in['public_key_jwk'] ?? null;
  $privEncB64 = $in['private_key_enc'] ?? null;
  $privIvB64 = $in['private_key_iv'] ?? null;

  // Password-based KEK encryption
  $kekEncB64 = $in['kek_enc'] ?? null;
  $kekIvB64 = $in['kek_iv'] ?? null;
  $saltB64 = $in['pwkdf_salt'] ?? null;
  $pwkdfIters = (int)($in['pwkdf_iterations'] ?? 0);

  // Recovery key-based KEK encryption
  $kekEncRkB64 = $in['kek_enc_rk'] ?? null;
  $kekRkIvB64 = $in['kek_rk_iv'] ?? null;
  $rkdfSaltB64 = $in['rkdf_salt'] ?? null;
  $rkdfIters = (int)($in['rkdf_iterations'] ?? 0);

  $keyVersion = (int)($in['key_version'] ?? 1);

  if (!$pubJwk || !$privEncB64 || !$privIvB64 || !$kekEncB64 || !$kekIvB64 || !$saltB64) {
    bad_with_audit(422, 'Missing crypto fields', $pdo, null, 'missing_crypto_fields');
  }

  if ($pwkdfIters < 100000) bad_with_audit(422, 'pwkdf_iterations too low', $pdo, null, 'pwkdf_iters_low', ['iters' => $pwkdfIters]);

  if (!$kekEncRkB64 || !$kekRkIvB64 || !$rkdfSaltB64) {
    bad_with_audit(422, 'Missing recovery key crypto fields', $pdo, null, 'missing_recovery_crypto_fields');
  }

  if ($rkdfIters < 100000) bad_with_audit(422, 'rkdf_iterations too low', $pdo, null, 'rkdf_iters_low', ['iters' => $rkdfIters]);

  $b64 = fn($s) => ($s && ($d = base64_decode($s, true)) !== false) ? $d : null;

  $privateKeyEnc = $b64($privEncB64);
  $privateKeyIv = $b64($privIvB64);
  $kekEnc = $b64($kekEncB64);
  $kekIv = $b64($kekIvB64);
  $pwkdfSalt = $b64($saltB64);

  $kekEncRk = $b64($kekEncRkB64);
  $kekRkIv = $b64($kekRkIvB64);
  $rkdfSalt = $b64($rkdfSaltB64);

  if ($privateKeyEnc === null) bad_with_audit(422, 'Invalid private_key_enc b64', $pdo, null, 'invalid_private_key_enc');
  if ($privateKeyIv === null || strlen($privateKeyIv) !== 12) bad_with_audit(422, 'private_key_iv must be 12 bytes', $pdo, null, 'invalid_private_key_iv');
  if ($kekEnc === null) bad_with_audit(422, 'Invalid kek_enc b64', $pdo, null, 'invalid_kek_enc');
  if ($kekIv === null || strlen($kekIv) !== 12) bad_with_audit(422, 'kek_iv must be 12 bytes', $pdo, null, 'invalid_kek_iv');
  if ($pwkdfSalt === null || strlen($pwkdfSalt) !== 16) bad_with_audit(422, 'pwkdf_salt must be 16 bytes', $pdo, null, 'invalid_pwkdf_salt');

  if ($kekEncRk === null) bad_with_audit(422, 'Invalid kek_enc_rk b64', $pdo, null, 'invalid_kek_enc_rk');
  if ($kekRkIv === null || strlen($kekRkIv) !== 16) bad_with_audit(422, 'kek_rk_iv must be 16 bytes', $pdo, null, 'invalid_kek_rk_iv');
  if ($rkdfSalt === null || strlen($rkdfSalt) !== 16) bad_with_audit(422, 'rkdf_salt must be 16 bytes', $pdo, null, 'invalid_rkdf_salt');

  if (strlen($privateKeyEnc) > 4096) bad_with_audit(422, 'private_key_enc too large', $pdo, null, 'private_key_enc_too_large', ['bytes' => strlen($privateKeyEnc)]);
  if (strlen($kekEnc) > 512) bad_with_audit(422, 'kek_enc too large', $pdo, null, 'kek_enc_too_large', ['bytes' => strlen($kekEnc)]);
  if (strlen($kekEncRk) > 512) bad_with_audit(422, 'kek_enc_rk too large', $pdo, null, 'kek_enc_rk_too_large', ['bytes' => strlen($kekEncRk)]);

  $pubJwkJson = json_encode($pubJwk, JSON_UNESCAPED_SLASHES);
  if ($pubJwkJson === false) bad_with_audit(422, 'public_key_jwk not serializable', $pdo, null, 'pubjwk_not_serializable');

  // Hash password using Argon2ID (or bcrypt fallback)
  if (defined('PASSWORD_ARGON2ID')) {
    $passwordHash = password_hash($password, PASSWORD_ARGON2ID, [
      'memory_cost' => 1 << 17, // 128MB
      'time_cost' => 4,
      'threads' => 2,
    ]);
  } else {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
  }

  if ($passwordHash === false) {
    bad_with_audit(500, 'Failed to hash password', $pdo, null, 'password_hash_failed');
  }

  $pdo->beginTransaction();

  // Check uniqueness
  $st = $pdo->prepare('SELECT 1 FROM users WHERE user_phone = :p LIMIT 1');
  $st->execute([':p' => $phoneE164]);
  if ($st->fetch()) {
    $pdo->rollBack();
    bad_with_audit(409, 'Phone number already registered', $pdo, null, 'duplicate_phone', ['phone' => $phoneE164]);
  }

  $st = $pdo->prepare('SELECT 1 FROM users WHERE username = :u LIMIT 1');
  $st->execute([':u' => $username]);
  if ($st->fetch()) {
    $pdo->rollBack();
    bad_with_audit(409, 'Username already taken', $pdo, null, 'duplicate_username', ['username' => $username]);
  }

  $st = $pdo->prepare('SELECT 1 FROM users WHERE user_email = :e LIMIT 1');
  $st->execute([':e' => $email]);
  if ($st->fetch()) {
    $pdo->rollBack();
    bad_with_audit(409, 'Email already registered', $pdo, null, 'duplicate_email', ['email' => $email]);
  }

  $sql = <<<SQL
INSERT INTO users
  (user_fullname, user_phone, user_email, username, user_password, status)
VALUES
  (:fullname, :phone, :email, :username, :pwdhash, 'inactive')
SQL;

  $ins = $pdo->prepare($sql);
  $ins->execute([
    ':fullname' => $fullName,
    ':phone' => $phoneE164,
    ':email' => $email,
    ':username' => $username,
    ':pwdhash' => $passwordHash,
  ]);

  $userId = (int)$pdo->lastInsertId();

  // Insert into user_crypto_keys table
  $sqlKeys = <<<SQL
INSERT INTO user_crypto_keys
  (user_id, public_key_jwk, private_key_enc, private_key_iv, key_version, key_status)
VALUES
  (:user_id, :pubjwk, :priv_enc, :priv_iv, :kver, 'active')
SQL;

  $insKeys = $pdo->prepare($sqlKeys);
  $insKeys->execute([
    ':user_id' => $userId,
    ':pubjwk' => $pubJwkJson,
    ':priv_enc' => $privateKeyEnc,
    ':priv_iv' => $privateKeyIv,
    ':kver' => $keyVersion ?: 1,
  ]);

  // Insert into user_kek table
  $sqlKek = <<<SQL
INSERT INTO user_kek
  (user_id, kek_enc, kek_iv, pwkdf_salt, pwkdf_iterations, kek_version, is_active)
VALUES
  (:user_id, :kek_enc, :kek_iv, :pw_salt, :pw_iters, 1, 1)
SQL;

  $insKek = $pdo->prepare($sqlKek);
  $insKek->execute([
    ':user_id' => $userId,
    ':kek_enc' => $kekEnc,
    ':kek_iv' => $kekIv,
    ':pw_salt' => $pwkdfSalt,
    ':pw_iters' => $pwkdfIters,
  ]);

  // Insert into user_recovery_keys table
  $sqlRecovery = <<<SQL
INSERT INTO user_recovery_keys
  (user_id, kek_enc_rk, kek_rk_iv, rkdf_salt, rkdf_iterations, recovery_key_version, is_active)
VALUES
  (:user_id, :kek_enc_rk, :kek_rk_iv, :rk_salt, :rk_iters, 1, 1)
SQL;

  $insRecovery = $pdo->prepare($sqlRecovery);
  $insRecovery->execute([
    ':user_id' => $userId,
    ':kek_enc_rk' => $kekEncRk,
    ':kek_rk_iv' => $kekRkIv,
    ':rk_salt' => $rkdfSalt,
    ':rk_iters' => $rkdfIters,
  ]);

  // Generate 6-digit OTP (never log/store OTP in audit)
  $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  $codeHash = password_hash($code, PASSWORD_DEFAULT);
  if ($codeHash === false) {
    $pdo->rollBack();
    bad_with_audit(500, 'Failed to generate code', $pdo, $userId, 'otp_generate_failed');
  }

  // Remove previous codes
  $pdo->prepare('DELETE FROM email_verification_codes WHERE user_id = :uid AND consumed_at IS NULL')
    ->execute([':uid' => $userId]);

  $expiresAt = (new DateTimeImmutable('+10 minutes'))->format('Y-m-d H:i:s');
  $pdo->prepare(
    'INSERT INTO email_verification_codes (user_id, code_hash, expires_at)
     VALUES (:uid, :ch, :exp)'
  )->execute([
    ':uid' => $userId,
    ':ch' => $codeHash,
    ':exp' => $expiresAt,
  ]);

  // AUDIT: OTP issued (store expires_at only)
  audit_log(
    $pdo,
    $userId,
    'EMAIL_VERIFICATION_OTP_SENT',
    'AUTH',
    'INFO',
    'Email verification OTP created and stored (hashed)',
    ['expires_at' => $expiresAt]
  );

  // Send verification email
  try {
    $mailerPath = __DIR__ . '/mailer.php';
    if (!file_exists($mailerPath)) {
      $mailerPath = __DIR__ . '/../core/mailer.php';
    }

    if (file_exists($mailerPath)) {
      /** @var PHPMailer\PHPMailer\PHPMailer $mail */
      $mail = require $mailerPath;

      $mail->clearAllRecipients();
      $mail->setFrom('multimediasecurefileshare@gmail.com', 'Secure File Sharing');
      $mail->addAddress($email, $fullName);
      $mail->Subject = 'Verify your email address';
      $mail->Body = <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
  <h2 style="color: #333;">Email Verification</h2>
  <p>Hi {$fullName},</p>
  <p>Thank you for registering! Your verification code is:</p>
  <div style="background: #f4f4f4; padding: 20px; text-align: center; margin: 20px 0; border-radius: 5px;">
    <p style="font-size:28px; font-weight:bold; letter-spacing:4px; color:#2563eb; margin:0;">{$code}</p>
  </div>
  <p>This code will expire in 10 minutes.</p>
  <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
  <p style="color: #666; font-size: 12px;">If you didn't request this, please ignore this email.</p>
</div>
HTML;
      $mail->AltBody = "Your verification code is: {$code}\nIt expires in 10 minutes.";

      $mail->send();
      error_log("✅ EMAIL SENT SUCCESSFULLY to: $email");
    }
  } catch (Throwable $e) {
    error_log("❌ EMAIL SEND ERROR: " . $e->getMessage());
    // Continue anyway - don't fail registration
  }

  $pdo->commit();

  // AUDIT: registration created (traceability)
  audit_log(
    $pdo,
    $userId,
    'USER_REGISTERED',
    'AUTH',
    'INFO',
    'New user registered (account inactive until email verified)',
    [
      'user_id' => $userId,
      'username' => $username,
      'email' => $email,
      // IP + UA are already captured in audit_log()
    ]
  );

  try {
    notifyUserRegistered($pdo, [
      'user_id' => $userId,
      'username' => $username,
      'email' => $email,
      'phone' => $phoneE164
    ]);
  } catch (Throwable $notifErr) {
    error_log('Failed to send admin notification: ' . $notifErr->getMessage());
  }

  error_log("Registration completed for user: $username (ID: $userId) with secure password hash");

  out(201, [
    'ok' => true,
    'user_id' => $userId,
    'needs_verification' => true,
    'message' => 'Registration successful. Check your email for the verification code.',
  ]);
} catch (PDOException $e) {
  // If we can, best-effort audit as REGISTER_REJECTED (DB errors are security-relevant)
  try {
    $pdoTmp = db();
    audit_log(
      $pdoTmp,
      null,
      'REGISTER_REJECTED',
      'DB',
      'HIGH',
      'Database exception during registration',
      ['pdo_code' => $e->getCode()]
    );
  } catch (Throwable $ignored) {
  }

  if ($pdo ?? null) {
    try {
      $pdo->rollBack();
    } catch (Throwable $ignored) {
    }
  }

  if ($e->getCode() === '23000') {
    $info = $e->errorInfo[2] ?? '';
    if (stripos($info, 'user_phone') !== false) out(409, ['ok' => false, 'message' => 'Phone number already registered']);
    if (stripos($info, 'username') !== false) out(409, ['ok' => false, 'message' => 'Username already taken']);
    if (stripos($info, 'user_email') !== false) out(409, ['ok' => false, 'message' => 'Email already registered']);
    out(409, ['ok' => false, 'message' => 'Duplicate entry']);
  }

  error_log('DB error: ' . $e->getMessage());
  out(500, ['ok' => false, 'message' => 'Database error']);
} catch (Throwable $e) {
  try {
    $pdoTmp = db();
    audit_log(
      $pdoTmp,
      null,
      'REGISTER_REJECTED',
      'SERVER',
      'HIGH',
      'Server exception during registration',
      ['error' => get_class($e)]
    );
  } catch (Throwable $ignored) {
  }

  if ($pdo ?? null) {
    try {
      $pdo->rollBack();
    } catch (Throwable $ignored) {
    }
  }

  error_log('Server error: ' . $e->getMessage());
  out(500, ['ok' => false, 'message' => 'Server error']);
}