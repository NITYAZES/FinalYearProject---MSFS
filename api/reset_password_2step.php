<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/config.php';
// ✅ NEW: user notifications
require_once __DIR__ . '/user_notification_helper.php';

/* -------------------- audit helpers -------------------- */
function get_client_ip(): string {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  return is_string($ip) ? $ip : '';
}

function get_user_agent(): string {
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  return is_string($ua) ? substr($ua, 0, 512) : '';
}

/**
 * security_audit_log(user_id,event_type,event_category,severity,description,user_agent,metadata_json,created_at)
 */
function audit_log(
  PDO $pdo,
  ?int $userId,
  string $eventType,
  string $category,
  string $severity,
  string $description,
  array $metadata = []
): void {
  try {
    $metadata = array_merge(['ip' => get_client_ip()], $metadata);

    $metaJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($metaJson === false) $metaJson = '{}';

    $stmt = $pdo->prepare(
      'INSERT INTO security_audit_log
        (user_id, event_type, event_category, severity, description, user_agent, metadata_json, created_at)
       VALUES
        (:uid, :etype, :cat, :sev, :descr, :ua, :meta, NOW())'
    );

    $stmt->execute([
      ':uid'   => $userId,
      ':etype' => $eventType,
      ':cat'   => $category,
      ':sev'   => $severity,
      ':descr' => $description,
      ':ua'    => get_user_agent(),
      ':meta'  => $metaJson,
    ]);
  } catch (Throwable $e) {
    error_log('security_audit_log insert failed: ' . $e->getMessage());
  }
}

/* -------------------- base URL -------------------- */
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host . '/FinalYearProject';

/* -------------------- GET: validate token then redirect -------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
  $token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';

  try {
    $pdo = db();

    if ($token === '') {
      audit_log(
        $pdo,
        null,
        'PASSWORD_RESET_TOKEN_MISSING',
        'AUTH',
        'LOW',
        'Password reset token missing on reset link access'
      );
      http_response_code(400);
      exit('Missing token');
    }

    // Never log the raw token; at most log a short fingerprint
    $tokenFingerprint = substr(hash('sha256', $token), 0, 12);

    $token_hash = hash('sha256', $token, true); // binary

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
      audit_log(
        $pdo,
        null,
        'PASSWORD_RESET_TOKEN_INVALID',
        'AUTH',
        'MEDIUM',
        'Password reset token invalid / already used',
        ['token_fp' => $tokenFingerprint]
      );
      http_response_code(400);
      exit('Invalid or expired link.');
    }

    $resetId   = (int)$req['reset_id'];
    $userId    = (int)$req['user_id'];
    $expiresAt = (string)($req['reset_token_expires_at'] ?? '');

    if ($expiresAt === '' || strtotime($expiresAt) <= time()) {
      // Cleanup expired row
      $del = $pdo->prepare('DELETE FROM password_reset_requests WHERE reset_id = ?');
      $del->execute([$resetId]);

      audit_log(
        $pdo,
        $userId,
        'PASSWORD_RESET_TOKEN_EXPIRED',
        'AUTH',
        'MEDIUM',
        'Password reset token expired',
        [
          'reset_id' => $resetId,
          'expires_at' => $expiresAt,
          'token_fp' => $tokenFingerprint
        ]
      );

      http_response_code(400);
      exit('Reset link has expired.');
    }

    audit_log(
      $pdo,
      $userId,
      'PASSWORD_RESET_TOKEN_ACCEPTED',
      'AUTH',
      'INFO',
      'Password reset token accepted; redirected to reset form',
      [
        'reset_id' => $resetId,
        'expires_at' => $expiresAt,
        'token_fp' => $tokenFingerprint
      ]
    );

    header('Location: ' . $baseUrl . '/reset_password_2step.html?token=' . urlencode($token));
    exit;

  } catch (Throwable $e) {
    error_log('reset_password redirect error: ' . $e->getMessage());
    http_response_code(500);
    exit('Server error');
  }
}

/* -------------------- POST: reject (legacy) -------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  http_response_code(400);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'success' => false,
    'message' => 'Please use /api/initiate_password_reset.php or /api/confirm_password_reset.php'
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

/* -------------------- other methods -------------------- */
http_response_code(405);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(
  ['success' => false, 'message' => 'Method not allowed'],
  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
exit;
