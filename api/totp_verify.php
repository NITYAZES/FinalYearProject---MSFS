<?php
declare(strict_types=1);

session_start();

// Production-safe defaults (disable debug output)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/totp_helper.php';
require_once __DIR__ . '/totp_encryption_helper.php';
require_once __DIR__ . '/admin_notification_helper.php';
require_once __DIR__ . '/user_notification_helper.php';

/**
 * Fallback json_out if not defined in config.php
 */
if (!function_exists('json_out')) {
    function json_out(int $code, array $payload): void {
        http_response_code($code);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/* -------------------- Request context helpers -------------------- */
function get_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? $ip : '';
}

function get_user_agent(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return is_string($ua) ? substr($ua, 0, 512) : '';
}

/* -------------------- Security audit logger (matches your table) -------------------- */
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

/**
 * Return 200 for user-caused errors (keeps frontend happy),
 * but still logs security events when relevant.
 */
function soft_fail(string $message, array $extra = []): void
{
    json_out(200, array_merge(['ok' => false, 'message' => $message], $extra));
}

/* -------------------- Auth check -------------------- */
if (empty($_SESSION['user_id']) || empty($_SESSION['logged_in'])) {
    json_out(401, ['ok' => false, 'message' => 'Not authenticated']);
}

$userId = (int)$_SESSION['user_id'];

/* -------------------- Read body -------------------- */
$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);

if (!is_array($input)) {
    soft_fail('Invalid request body.');
}

$code = trim((string)($input['code'] ?? ''));

// Prefer secret from session (set by totp_init.php), fallback to client
$sessionSecret = trim((string)($_SESSION['temp_totp_secret'] ?? ''));
$secret = $sessionSecret !== '' ? $sessionSecret : trim((string)($input['secret'] ?? ''));

/* -------------------- Validate input -------------------- */
if ($code === '') soft_fail('Missing required field: code');

if ($secret === '') {
    // Setup session expired (missing secret)
    try {
        $pdoTmp = db();
        audit_log(
            $pdoTmp,
            $userId ?: null,
            'TOTP_SETUP_SESSION_EXPIRED',
            'AUTH',
            'MEDIUM',
            'TOTP setup session expired (missing secret)'
        );
    } catch (Throwable $ignored) {}
    soft_fail('Session expired. Please restart setup.');
}

if (!preg_match('/^\d{6}$/', $code)) {
    soft_fail('Invalid code format. Must be 6 digits.');
}

$backupCodes = $_SESSION['temp_backup_codes'] ?? [];
if (!is_array($backupCodes) || empty($backupCodes)) {
    try {
        $pdoTmp = db();
        audit_log(
            $pdoTmp,
            $userId ?: null,
            'TOTP_SETUP_SESSION_EXPIRED',
            'AUTH',
            'MEDIUM',
            'TOTP setup session expired (missing backup codes)'
        );
    } catch (Throwable $ignored) {}
    soft_fail('Session expired. Please restart setup.');
}

/* -------------------- Verify TOTP (allow small drift) -------------------- */
if (!verifyTotpCode($secret, $code, 2)) {
    try {
        $pdoTmp = db();
        audit_log(
            $pdoTmp,
            $userId,
            'TOTP_VERIFY_FAILED',
            'AUTH',
            'MEDIUM',
            'Invalid TOTP code during 2FA setup'
        );
    } catch (Throwable $ignored) {}
    soft_fail('Invalid code. Please try again.');
}

/* -------------------- Persist: ONLY user_mfa_totp stores backup codes -------------------- */
$pdo = null;

try {
    $pdo = db();

    // Username for admin notification (optional)
    $userStmt = $pdo->prepare('SELECT username FROM users WHERE user_id = :id LIMIT 1');
    $userStmt->execute([':id' => $userId]);
    $userRow  = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $username = (string)($userRow['username'] ?? 'Unknown');

    // Encrypt secret for storage (ciphertext + tag in one blob)
    $encrypted = encryptTotpSecret($secret);
    $encryptedData = $encrypted['ciphertext'] . $encrypted['tag'];

    // Hash backup codes (bcrypt is OK for codes)
    $hashedBackupCodes = array_map(
        static fn($c) => password_hash((string)$c, PASSWORD_BCRYPT),
        $backupCodes
    );

    $pdo->beginTransaction();

    // 1) Keep users.totp_enabled (DO NOT store backup codes in users anymore)
    $updateUserStmt = $pdo->prepare('
        UPDATE users
        SET totp_enabled = 1
        WHERE user_id = :user_id
        LIMIT 1
    ');
    $updateUserStmt->execute([':user_id' => $userId]);

    // 2) Store secret + backup codes in user_mfa_totp (single source of truth)
    //    Recommended: user_mfa_totp.user_id should be UNIQUE
    $mfaUpsert = $pdo->prepare('
        INSERT INTO user_mfa_totp
            (user_id, totp_secret_enc, totp_secret_iv, backup_codes, is_enabled, confirmed_at, enabled_at, created_at, updated_at)
        VALUES
            (:user_id, :secret_enc, :secret_iv, :backup_codes, 1, NOW(), NOW(), NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            totp_secret_enc = VALUES(totp_secret_enc),
            totp_secret_iv  = VALUES(totp_secret_iv),
            backup_codes    = VALUES(backup_codes),
            is_enabled      = 1,
            confirmed_at    = NOW(),
            enabled_at      = NOW(),
            updated_at      = NOW()
    ');
    $mfaUpsert->execute([
        ':user_id'      => $userId,
        ':secret_enc'   => $encryptedData,
        ':secret_iv'    => $encrypted['iv'],
        ':backup_codes' => json_encode($hashedBackupCodes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    // 3) AUDIT: 2FA enabled
    audit_log(
        $pdo,
        $userId,
        'TOTP_ENABLED',
        'SECURITY',
        'INFO',
        'User enabled 2FA (TOTP) successfully',
        [
            'method' => 'totp',
            'has_backup_codes' => true
        ]
    );

    // 4) Admin + user notifications (optional)
    try {
        if (function_exists('notifyAdmin2FAStatusChanged')) {
            notifyAdmin2FAStatusChanged($pdo, $userId, $username, true);
        }
    } catch (Throwable $e) {
        error_log('Admin notification skipped: ' . $e->getMessage());
    }

    try {
        if (function_exists('notify2FAStatusChanged')) {
            notify2FAStatusChanged($pdo, $userId, true);
        }
    } catch (Throwable $e) {
        error_log('User notification skipped: ' . $e->getMessage());
    }

    $pdo->commit();

    // Clear temp session data
    unset($_SESSION['temp_totp_secret'], $_SESSION['temp_backup_codes']);
    $_SESSION['totp_enabled'] = true;

    json_out(200, [
        'ok'      => true,
        'message' => 'Two-factor authentication enabled successfully!',
    ]);

} catch (Throwable $e) {
    if ($pdo instanceof PDO) {
        try {
            if ($pdo->inTransaction()) $pdo->rollBack();
        } catch (Throwable $ignored) {}
    }

    // Best-effort audit
    try {
        $pdoTmp = db();
        audit_log(
            $pdoTmp,
            $userId ?: null,
            'TOTP_ENABLE_FAILED',
            'SERVER',
            'HIGH',
            'Server error while enabling 2FA (TOTP)',
            ['error' => get_class($e)]
        );
    } catch (Throwable $ignored) {}

    error_log('TOTP verify error: ' . $e->getMessage());

    json_out(500, [
        'ok'      => false,
        'message' => 'An error occurred while enabling 2FA',
    ]);
}
