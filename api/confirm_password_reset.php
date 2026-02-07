<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // Do not show errors to users
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../debug.log');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=utf-8');

function json_response(bool $success, string $message, array $data = [], int $code = 200): void
{
  http_response_code($code);
  echo json_encode(
    array_merge(['success' => $success, 'message' => $message], $data),
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
  );
  exit;
}

// Only POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  error_log('[confirm_password_reset] Invalid request method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'NONE'));
  json_response(false, 'Invalid request method', [], 405);
}

// CSRF validation
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!csrf_verify($csrfToken)) {
  error_log('[confirm_password_reset] CSRF validation failed. Token: ' . var_export($csrfToken, true));
  json_response(false, 'CSRF validation failed', [], 403);
}

try {
  $input = file_get_contents('php://input');
  if ($input === false || $input === '') {
    error_log('[confirm_password_reset] No input received');
    json_response(false, 'No input data received', [], 400);
  }

  // Anti-DoS request size limit (1MB)
  if (strlen($input) > 1_000_000) {
    error_log('[confirm_password_reset] Payload too large: ' . strlen($input) . ' bytes');
    json_response(false, 'Payload too large', [], 413);
  }

  // Log raw input for debugging
  error_log('[confirm_password_reset] Raw input length: ' . strlen($input));
  error_log('[confirm_password_reset] Raw input preview: ' . substr($input, 0, 200));

  $data = json_decode($input, true);
  if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
    error_log('[confirm_password_reset] Invalid JSON. Error: ' . json_last_error_msg());
    json_response(false, 'Invalid JSON: ' . json_last_error_msg(), [], 400);
  }

  error_log('[confirm_password_reset] Parsed data keys: ' . implode(', ', array_keys($data)));

  $token = trim((string)($data['token'] ?? ''));
  $otp   = trim((string)($data['otp'] ?? ''));

  // New recovery key materials (base64)
  $newKekEncRkB64 = $data['new_kek_enc_rk'] ?? null;
  $newKekRkIvB64  = $data['new_kek_rk_iv'] ?? null;
  $newRkdfSaltB64 = $data['new_rkdf_salt'] ?? null;
  $newRkdfIters   = (int)($data['new_rkdf_iterations'] ?? 0);

  // Validation with detailed logging
  if ($token === '') {
    error_log('[confirm_password_reset] Missing token');
    json_response(false, 'Missing token', [], 400);
  }

  if ($otp === '') {
    error_log('[confirm_password_reset] Missing OTP');
    json_response(false, 'Missing OTP', [], 400);
  }

  if (!preg_match('/^\d{6}$/', $otp)) {
    error_log('[confirm_password_reset] Invalid OTP format: ' . $otp);
    json_response(false, 'OTP must be 6 digits', [], 400);
  }

  if (!$newKekEncRkB64) {
    error_log('[confirm_password_reset] Missing new_kek_enc_rk');
    json_response(false, 'Missing new recovery key encryption', [], 400);
  }

  if (!$newKekRkIvB64) {
    error_log('[confirm_password_reset] Missing new_kek_rk_iv');
    json_response(false, 'Missing new recovery key IV', [], 400);
  }

  if (!$newRkdfSaltB64) {
    error_log('[confirm_password_reset] Missing new_rkdf_salt');
    json_response(false, 'Missing new recovery key salt', [], 400);
  }

  if ($newRkdfIters < 100000) {
    error_log('[confirm_password_reset] rkdf_iterations too low: ' . $newRkdfIters);
    json_response(false, 'Recovery key iterations must be at least 100,000', [], 400);
  }

  // Decode new recovery key materials
  $newKekEncRk = base64_decode((string)$newKekEncRkB64, true);
  $newKekRkIv  = base64_decode((string)$newKekRkIvB64, true);
  $newRkdfSalt = base64_decode((string)$newRkdfSaltB64, true);

  if ($newKekEncRk === false) {
    error_log('[confirm_password_reset] Invalid base64 for new_kek_enc_rk');
    json_response(false, 'Invalid base64 encoding for encrypted recovery key', [], 400);
  }

  if ($newKekRkIv === false) {
    error_log('[confirm_password_reset] Invalid base64 for new_kek_rk_iv');
    json_response(false, 'Invalid base64 encoding for recovery key IV', [], 400);
  }

  if ($newRkdfSalt === false) {
    error_log('[confirm_password_reset] Invalid base64 for new_rkdf_salt');
    json_response(false, 'Invalid base64 encoding for recovery key salt', [], 400);
  }

  // Allow 12-byte IV (preferred) OR 16-byte IV (legacy)
  $ivLen = strlen($newKekRkIv);
  if ($ivLen !== 12 && $ivLen !== 16) {
    error_log('[confirm_password_reset] Invalid IV length: ' . $ivLen);
    json_response(false, 'Invalid IV length (must be 12 or 16 bytes)', [], 400);
  }

  if (strlen($newRkdfSalt) !== 16) {
    error_log('[confirm_password_reset] Invalid salt length: ' . strlen($newRkdfSalt));
    json_response(false, 'Invalid salt length (must be 16 bytes)', [], 400);
  }

  $encLen = strlen($newKekEncRk);
  if ($encLen < 32 || $encLen > 512) {
    error_log('[confirm_password_reset] Invalid encrypted KEK length: ' . $encLen);
    json_response(false, 'Invalid encrypted recovery key length', [], 400);
  }

  error_log('[confirm_password_reset] All validations passed, proceeding with database operations');

  $pdo = db();

  // Hash token + otp (binary)
  $tokenHash = hash('sha256', $token, true);
  $otpHash   = hash('sha256', $otp, true);

  $pdo->beginTransaction();

  // Look up pending reset (must match reset token + otp hash)
  $stmt = $pdo->prepare(
    'SELECT p.pending_id, p.user_id, p.reset_request_id,
            p.new_kek_enc, p.new_kek_iv, p.new_pwkdf_salt,
            p.new_pwkdf_iterations, p.new_password_hash,
            p.expires_at, p.confirmed_at
       FROM password_reset_pending p
       JOIN password_reset_requests r ON p.reset_request_id = r.reset_id
      WHERE r.reset_token_hash = ?
        AND p.confirmation_token_hash = ?
        AND p.confirmed_at IS NULL
      ORDER BY p.pending_id DESC
      LIMIT 1'
  );

  $stmt->execute([$tokenHash, $otpHash]);
  $pending = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$pending) {
    error_log('[confirm_password_reset] No pending reset found for token/OTP combination');
    $pdo->rollBack();
    json_response(false, 'Invalid or expired confirmation code', [], 400);
  }

  error_log('[confirm_password_reset] Found pending reset ID: ' . $pending['pending_id']);

  // Check expiry
  $expiresAt = (string)($pending['expires_at'] ?? '');
  if ($expiresAt === '' || strtotime($expiresAt) <= time()) {
    error_log('[confirm_password_reset] Confirmation code expired at: ' . $expiresAt);
    $del = $pdo->prepare('DELETE FROM password_reset_pending WHERE pending_id = ?');
    $del->execute([(int)$pending['pending_id']]);
    $pdo->commit();
    json_response(false, 'Confirmation code has expired. Please restart the reset process', [], 400);
  }

  $userId    = (int)$pending['user_id'];
  $pendingId = (int)$pending['pending_id'];
  $resetId   = (int)$pending['reset_request_id'];

  error_log('[confirm_password_reset] Processing reset for user ID: ' . $userId);

  // Current versions
  $kekStmt = $pdo->prepare('SELECT kek_version FROM user_kek WHERE user_id = ? AND is_active = 1 LIMIT 1');
  $kekStmt->execute([$userId]);
  $kekRow = $kekStmt->fetch(PDO::FETCH_ASSOC);
  $currentKekVersion = (int)($kekRow['kek_version'] ?? 0);

  $recoveryStmt = $pdo->prepare('SELECT recovery_key_version FROM user_recovery_keys WHERE user_id = ? AND is_active = 1 LIMIT 1');
  $recoveryStmt->execute([$userId]);
  $rkRow = $recoveryStmt->fetch(PDO::FETCH_ASSOC);
  $currentRecoveryVersion = (int)($rkRow['recovery_key_version'] ?? 0);

  error_log('[confirm_password_reset] Current KEK version: ' . $currentKekVersion);
  error_log('[confirm_password_reset] Current recovery key version: ' . $currentRecoveryVersion);

  // Update password
  $updatePwStmt = $pdo->prepare(
    'UPDATE users
        SET user_password = ?,
            updated_at = NOW()
      WHERE user_id = ?'
  );

  if (!$updatePwStmt->execute([(string)$pending['new_password_hash'], $userId])) {
    error_log('[confirm_password_reset] Failed to update password');
    $pdo->rollBack();
    json_response(false, 'Failed to update password', [], 500);
  }

  error_log('[confirm_password_reset] Password updated successfully');

  // Rotate KEK
  $pdo->prepare('UPDATE user_kek SET is_active = 0, rotated_at = NOW() WHERE user_id = ?')
      ->execute([$userId]);

  $insertKekStmt = $pdo->prepare(
    'INSERT INTO user_kek
        (user_id, kek_enc, kek_iv, pwkdf_salt, pwkdf_iterations, kek_version, is_active)
     VALUES (?, ?, ?, ?, ?, ?, 1)'
  );

  if (!$insertKekStmt->execute([
    $userId,
    $pending['new_kek_enc'],
    $pending['new_kek_iv'],
    $pending['new_pwkdf_salt'],
    $pending['new_pwkdf_iterations'],
    $currentKekVersion + 1
  ])) {
    error_log('[confirm_password_reset] Failed to insert new KEK');
    $pdo->rollBack();
    json_response(false, 'Failed to update encryption keys', [], 500);
  }

  error_log('[confirm_password_reset] KEK rotated successfully');

  // Rotate recovery key materials
  $pdo->prepare('UPDATE user_recovery_keys SET is_active = 0, rotated_at = NOW() WHERE user_id = ?')
      ->execute([$userId]);

  $insertRecoveryStmt = $pdo->prepare(
    'INSERT INTO user_recovery_keys
        (user_id, kek_enc_rk, kek_rk_iv, rkdf_salt, rkdf_iterations, recovery_key_version, is_active)
     VALUES (?, ?, ?, ?, ?, ?, 1)'
  );

  if (!$insertRecoveryStmt->execute([
    $userId,
    $newKekEncRk,
    $newKekRkIv,
    $newRkdfSalt,
    $newRkdfIters,
    $currentRecoveryVersion + 1
  ])) {
    error_log('[confirm_password_reset] Failed to insert new recovery key');
    $pdo->rollBack();
    json_response(false, 'Failed to update recovery keys', [], 500);
  }

  error_log('[confirm_password_reset] Recovery key rotated successfully');

  // Mark pending reset confirmed + mark reset token used + cleanup other pendings
  $pdo->prepare('UPDATE password_reset_pending SET confirmed_at = NOW() WHERE pending_id = ?')
      ->execute([$pendingId]);

  $pdo->prepare('UPDATE password_reset_requests SET used_at = NOW() WHERE reset_id = ?')
      ->execute([$resetId]);

  $pdo->prepare('DELETE FROM password_reset_pending WHERE user_id = ? AND pending_id != ?')
      ->execute([$userId, $pendingId]);

  $pdo->commit();

  error_log('[confirm_password_reset] Transaction committed successfully');

  // Audit log (optional) - runs AFTER commit
  try {
    $meta = [
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
      'reset_id' => $resetId,
      'pending_id' => $pendingId,
      'new_kek_version' => $currentKekVersion + 1,
      'new_recovery_key_version' => $currentRecoveryVersion + 1,
    ];

    $logStmt = $pdo->prepare('
      INSERT INTO security_audit_log
        (user_id, event_type, event_category, severity, description, user_agent, metadata_json, created_at)
      VALUES
        (:uid, :etype, :cat, :sev, :descr, :ua, :meta, NOW())
    ');

    $logStmt->execute([
      ':uid'   => $userId,
      ':etype' => 'password_reset_complete',
      ':cat'   => 'security',
      ':sev'   => 'warning',
      ':descr' => 'Password reset completed via recovery key. KEK and recovery key rotated.',
      ':ua'    => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 512),
      ':meta'  => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    error_log('[confirm_password_reset] Audit log written successfully');
  } catch (Throwable $e) {
    error_log('[confirm_password_reset] audit log failed: ' . $e->getMessage());
  }

  json_response(true, 'Password reset successful! Your recovery key has been rotated for security.', [
    'recovery_key_rotated' => true,
    'new_recovery_key_version' => $currentRecoveryVersion + 1,
    'new_kek_version' => $currentKekVersion + 1
  ], 200);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
    $pdo->rollBack();
  }

  error_log('[confirm_password_reset] ERROR: ' . $e->getMessage());
  error_log('[confirm_password_reset] File: ' . $e->getFile() . ':' . $e->getLine());
  error_log('[confirm_password_reset] Trace: ' . $e->getTraceAsString());

  json_response(false, 'Server error. Please try again later.', [], 500);
}