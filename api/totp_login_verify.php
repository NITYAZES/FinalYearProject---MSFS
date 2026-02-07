<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/totp_helper.php';
require_once __DIR__ . '/totp_encryption_helper.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/* -------------------- helpers -------------------- */
function out(int $code, array $payload): never
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

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
        error_log('Audit log insert failed: ' . $e->getMessage());
    }
}

$pdo = db();

/* -------------------- pending session check -------------------- */
if (!isset($_SESSION['pending_totp_user_id'])) {
    audit_log(
        $pdo,
        (int)($_SESSION['user_id'] ?? 0) ?: null,
        'TOTP_LOGIN_NO_PENDING',
        'AUTH',
        'MEDIUM',
        'TOTP verification requested without pending session'
    );

    out(401, [
        'ok' => false,
        'message' => 'No pending authentication. Please log in with your password first.',
    ]);
}

/* -------------------- read request -------------------- */
$rawInput = file_get_contents('php://input');
$input = json_decode((string)$rawInput, true);

if (!is_array($input)) {
    audit_log(
        $pdo,
        (int)($_SESSION['pending_totp_user_id'] ?? 0) ?: null,
        'TOTP_LOGIN_INVALID_JSON',
        'AUTH',
        'MEDIUM',
        'Invalid JSON in TOTP verification request'
    );

    out(400, ['ok' => false, 'message' => 'Invalid JSON input']);
}

$code = (string)($input['code'] ?? '');
$type = (string)($input['type'] ?? 'totp'); // 'totp' or 'backup'
$code = trim($code);
$type = trim($type);

if ($code === '') {
    out(200, ['ok' => false, 'message' => 'Code is required']);
}

if ($type !== 'totp' && $type !== 'backup') {
    out(200, ['ok' => false, 'message' => 'Invalid type']);
}

/* -------------------- fetch user + encrypted MFA -------------------- */
$pendingUid = (int)$_SESSION['pending_totp_user_id'];

$stmt = $pdo->prepare('
    SELECT u.user_id, u.username, u.user_email, u.user_fullname, u.role,
           mfa.totp_secret_enc, mfa.totp_secret_iv, mfa.backup_codes, mfa.is_enabled
    FROM users u
    JOIN user_mfa_totp mfa ON u.user_id = mfa.user_id
    WHERE u.user_id = :user_id AND mfa.is_enabled = 1
    LIMIT 1
');
$stmt->execute([':user_id' => $pendingUid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    audit_log(
        $pdo,
        $pendingUid,
        'TOTP_LOGIN_USER_NOT_FOUND',
        'AUTH',
        'MEDIUM',
        'User not found or 2FA not enabled during TOTP verification'
    );

    out(404, ['ok' => false, 'message' => 'User not found or 2FA not enabled']);
}

/* -------------------- decrypt TOTP secret -------------------- */
if (empty($user['totp_secret_enc']) || empty($user['totp_secret_iv'])) {
    audit_log(
        $pdo,
        (int)$user['user_id'],
        'TOTP_CONFIG_ERROR',
        'AUTH',
        'HIGH',
        'Missing encrypted TOTP secret or IV in DB'
    );

    out(500, ['ok' => false, 'message' => '2FA configuration error. Please contact support.']);
}

$encryptedData = (string)$user['totp_secret_enc'];
$iv = (string)$user['totp_secret_iv'];

try {
    $tag = substr($encryptedData, -16);
    $ciphertext = substr($encryptedData, 0, -16);

    $totpSecret = decryptTotpSecret($ciphertext, $iv, $tag);
} catch (Throwable $e) {
    audit_log(
        $pdo,
        (int)$user['user_id'],
        'TOTP_DECRYPT_FAILED',
        'SECURITY',
        'HIGH',
        'Failed to decrypt TOTP secret during login',
        ['error' => get_class($e)]
    );

    out(500, ['ok' => false, 'message' => 'Failed to decrypt authentication data. Please contact support.']);
}

/* -------------------- verify -------------------- */
$verified = false;

if ($type === 'totp') {
    $verified = verifyTotpCode($totpSecret, $code, 2);
} else {
    $backupCodes = json_decode((string)$user['backup_codes'], true);
    if (!is_array($backupCodes)) $backupCodes = [];

    foreach ($backupCodes as $index => $hashedCode) {
        if (is_string($hashedCode) && password_verify($code, $hashedCode)) {
            $verified = true;

            // Remove used backup code
            unset($backupCodes[$index]);
            $backupCodes = array_values($backupCodes);

            $upd = $pdo->prepare('UPDATE user_mfa_totp SET backup_codes = :codes WHERE user_id = :user_id');
            $upd->execute([
                ':codes' => json_encode($backupCodes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':user_id' => (int)$user['user_id']
            ]);

            audit_log(
                $pdo,
                (int)$user['user_id'],
                'TOTP_BACKUP_CODE_USED',
                'SECURITY',
                'INFO',
                'Backup code used for 2FA login',
                ['remaining_backup_codes' => count($backupCodes)]
            );

            break;
        }
    }
}

if (!$verified) {
    audit_log(
        $pdo,
        (int)$user['user_id'],
        'TOTP_LOGIN_FAILED',
        'AUTH',
        'MEDIUM',
        '2FA verification failed during login',
        ['type' => $type]
    );

    out(200, [
        'ok' => false,
        'message' => $type === 'backup'
            ? 'Invalid or already used backup code'
            : 'Invalid authentication code'
    ]);
}

/* -------------------- success: promote session -------------------- */
unset($_SESSION['pending_totp_user_id'], $_SESSION['pending_totp_username'], $_SESSION['pending_totp_role'], $_SESSION['pending_totp_time']);

$_SESSION['user_id'] = (int)$user['user_id'];
$_SESSION['username'] = (string)$user['username'];
$_SESSION['user_email'] = (string)$user['user_email'];
$_SESSION['role'] = (string)$user['role'];
$_SESSION['totp_enabled'] = true;
$_SESSION['logged_in'] = true;
$_SESSION['login_time'] = time();

audit_log(
    $pdo,
    (int)$user['user_id'],
    'TOTP_LOGIN_SUCCESS',
    'AUTH',
    'INFO',
    'User logged in successfully with 2FA',
    ['type' => $type]
);

out(200, [
    'ok' => true,
    'message' => 'Authentication successful',
    'user' => [
        'user_id' => (int)$user['user_id'],
        'username' => (string)$user['username'],
        'user_email' => (string)$user['user_email'],
        'user_fullname' => (string)($user['user_fullname'] ?? $user['username']),
        'role' => (string)$user['role'],
    ],
]);