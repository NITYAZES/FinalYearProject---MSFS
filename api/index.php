<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin_notification_helper.php';
require_once __DIR__ . '/user_notification_helper.php';


error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/* -------------------- Session hardening -------------------- */
// IMPORTANT: cookie_samesite must be "Lax" or "Strict" (not "1")
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');

// Set to "1" when deployed on HTTPS. Keep "0" only for local HTTP testing.
ini_set('session.cookie_secure', '0');

header('Content-Type: application/json; charset=utf-8');
session_start();

function out(int $code, array $payload): never
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function bad(int $code, string $msg): never
{
    out($code, ['ok' => false, 'message' => $msg]);
    exit;
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

/* -------------------- Security audit logger (consistent) -------------------- */
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
        // Always include request context (no secrets)
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
            ':uid'   => $userId,
            ':etype' => (string)$eventType,
            ':cat'   => (string)$category,
            ':sev'   => (string)$severity,
            ':descr' => (string)$description,
            ':ua'    => get_user_agent(),
            ':meta'  => $metaJson,
        ]);
    } catch (Throwable $e) {
        // Never break login due to audit failure
        error_log('Audit log insert failed: ' . $e->getMessage());
    }
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        out(405, ['ok' => false, 'message' => 'Method not allowed']);
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        out(400, ['ok' => false, 'message' => 'No input']);
    }

    // Optional basic request size limit
    if (strlen($raw) > 100_000) {
        out(413, ['ok' => false, 'message' => 'Payload too large']);
    }

    $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    $usernameRaw = trim((string)($input['username'] ?? ''));
    $password    = (string)($input['password'] ?? '');

    if ($usernameRaw === '') out(422, ['ok' => false, 'message' => 'Username is required']);
    if ($password === '') out(422, ['ok' => false, 'message' => 'Password is required']);
    if (strlen($password) < 12) out(422, ['ok' => false, 'message' => 'Password must be at least 12 characters']);

    $pdo = db();

    // Lookup user
    $stmt = $pdo->prepare(
        'SELECT 
            u.user_id,
            u.user_fullname,
            u.username,
            u.user_phone,
            u.user_email,
            u.user_password,
            u.role,
            u.status,
            u.created_at,
            u.email_verified_at,
            u.login_attempts,
            u.account_locked_until,
            mfa.is_enabled as totp_enabled,
            mfa.totp_secret_enc
         FROM users u
         LEFT JOIN user_mfa_totp mfa ON u.user_id = mfa.user_id
         WHERE LOWER(u.username) = LOWER(:username)
         LIMIT 1'
    );
    $stmt->execute([':username' => $usernameRaw]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // AUDIT: unknown username (possible enumeration / brute force)
        audit_log(
            $pdo,
            null,
            'POSSIBLE_ENUMERATION',
            'ENUMERATION',
            'MEDIUM',
            'Login attempt for non-existent username',
            ['username' => $usernameRaw]
        );

        out(401, ['ok' => false, 'message' => 'Invalid username or password']);
    }

    $uid = (int)$user['user_id'];

    // Account locked check
    $lockedUntil = $user['account_locked_until'];
    if ($lockedUntil && strtotime((string)$lockedUntil) > time()) {
        $remainingMinutes = (int)ceil((strtotime((string)$lockedUntil) - time()) / 60);

        audit_log(
            $pdo,
            $uid,
            'LOGIN_BLOCKED_LOCKED_ACCOUNT',
            'AUTH',
            'MEDIUM',
            'Login blocked due to locked account',
            [
                'locked_until' => $lockedUntil,
                'remaining_minutes' => $remainingMinutes,
            ]
        );

        out(423, [
            'ok' => false,
            'locked' => true,
            'message' => "Account is locked due to too many failed login attempts. Please try again in {$remainingMinutes} minute(s).",
            'locked_until' => $lockedUntil,
            'remaining_minutes' => $remainingMinutes
        ]);
    }

    // Status checks (audit)
    if ($user['status'] !== 'active') {
        if ($user['status'] === 'suspended') {
            audit_log($pdo, $uid, 'LOGIN_BLOCKED_SUSPENDED', 'AUTH', 'MEDIUM', 'Login blocked: user suspended');
            out(403, ['ok' => false, 'message' => 'Your account has been suspended. Please contact support.']);
        }

        audit_log($pdo, $uid, 'LOGIN_BLOCKED_INACTIVE', 'AUTH', 'MEDIUM', 'Login blocked: user inactive', [
            'status' => $user['status']
        ]);
        out(403, ['ok' => false, 'message' => 'Your account is inactive. Please contact support.']);
    }

    // Email verification check (audit)
    if (empty($user['email_verified_at'])) {
        audit_log($pdo, $uid, 'LOGIN_BLOCKED_EMAIL_UNVERIFIED', 'AUTH', 'MEDIUM', 'Login blocked: email not verified');
        out(403, ['ok' => false, 'message' => 'Your email address is not verified. Please verify your email first.']);
    }

    // Password verify
    if (!password_verify($password, (string)$user['user_password'])) {
        $attempts = (int)$user['login_attempts'] + 1;
        $maxAttempts = 5;

        if ($attempts >= $maxAttempts) {
            // Lock for 30 minutes + reset attempts
            $lockStmt = $pdo->prepare('
                UPDATE users 
                SET login_attempts = 0,
                    account_locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                WHERE user_id = :id
            ');
            $lockStmt->execute([':id' => $uid]);

            // Admin notify (existing behavior)
            try {
                notifyAccountLocked(
                    $pdo,
                    $uid,
                    (string)$user['username'],
                    "Too many failed login attempts ({$maxAttempts})"
                );
            } catch (Throwable $notifErr) {
                error_log('Failed to send account lock notification: ' . $notifErr->getMessage());
            }

            // AUDIT: account locked
            audit_log(
                $pdo,
                $uid,
                'ACCOUNT_LOCKED',
                'SECURITY',
                'HIGH',
                'Account locked due to too many failed login attempts',
                [
                    'attempts' => $attempts,
                    'max_attempts' => $maxAttempts,
                    'username' => $user['username'],
                ]
            );

            out(423, [
                'ok' => false,
                'locked' => true,
                'message' => 'Too many failed login attempts. Your account has been locked for 30 minutes for security reasons.'
            ]);
        }

        // Increment attempt counter
        $attemptStmt = $pdo->prepare('
            UPDATE users 
            SET login_attempts = :attempts
            WHERE user_id = :id
        ');
        $attemptStmt->execute([
            ':attempts' => $attempts,
            ':id' => $uid
        ]);

        // Admin notify (existing behavior) after 3+
        if ($attempts >= 3) {
            try {
                notifyFailedLoginAttempts(
                    $pdo,
                    (string)$user['username'],
                    $uid,
                    $attempts,
                    get_client_ip() !== '' ? get_client_ip() : 'unknown'
                );
            } catch (Throwable $notifErr) {
                error_log('Failed to send failed login notification: ' . $notifErr->getMessage());
            }
        }

        // AUDIT: failed login
        audit_log(
            $pdo,
            $uid,
            'LOGIN_FAILED',
            'AUTH',
            'MEDIUM',
            'Failed login attempt (bad password)',
            [
                'attempts' => $attempts,
                'max_attempts' => $maxAttempts,
            ]
        );

        $remaining = $maxAttempts - $attempts;

        out(401, [
            'ok' => false,
            'message' => "Invalid username or password. {$remaining} attempt(s) remaining before account lock.",
            'attempts_remaining' => $remaining,
            'max_attempts' => $maxAttempts
        ]);
    }

    // Password correct -> reset attempts and update last login
    $resetStmt = $pdo->prepare('
        UPDATE users 
        SET login_attempts = 0,
            account_locked_until = NULL,
            last_login_at = NOW()
        WHERE user_id = :id
    ');
    $resetStmt->execute([':id' => $uid]);

    // Rehash if needed
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    if (password_needs_rehash((string)$user['user_password'], $algo)) {
        $newHash = password_hash($password, $algo);
        if ($newHash !== false) {
            $update  = $pdo->prepare('UPDATE users SET user_password = :hash WHERE user_id = :id');
            $update->execute([':hash' => $newHash, ':id' => $uid]);
        }
    }

    // ✅ Check for suspicious login patterns (BEFORE TOTP or session creation)
    $currentIp = get_client_ip();
    $currentUserAgent = get_user_agent();

    // Get last successful login info
    $lastLoginStmt = $pdo->prepare('
        SELECT metadata_json FROM security_audit_log
        WHERE user_id = :user_id 
          AND event_type = "LOGIN_SUCCESS"
        ORDER BY created_at DESC
        LIMIT 1
    ');
    $lastLoginStmt->execute([':user_id' => $uid]);
    $lastLogin = $lastLoginStmt->fetch(PDO::FETCH_ASSOC);

    // Notify if different IP (potential account compromise)
    if ($lastLogin && !empty($lastLogin['metadata_json'])) {
        $lastMetadata = json_decode($lastLogin['metadata_json'], true);
        $lastIp = $lastMetadata['ip'] ?? null;
        
        if ($lastIp && $lastIp !== $currentIp && $currentIp !== '') {
            try {
                notifySuspiciousLogin(
                    $pdo,
                    $uid,
                    $currentIp,
                    $currentUserAgent
                );
                
                error_log("⚠️ Suspicious login detected for user {$uid}: IP changed from {$lastIp} to {$currentIp}");
            } catch (Throwable $notifErr) {
                error_log('Failed to send suspicious login notification: ' . $notifErr->getMessage());
            }
        }
    }

    // If TOTP enabled: pending step (audit)
    if ((int)$user['totp_enabled'] === 1) {
        $_SESSION['pending_totp_user_id'] = $uid;
        $_SESSION['pending_totp_username'] = $user['username'];
        $_SESSION['pending_totp_role']     = $user['role'];
        $_SESSION['pending_totp_time']     = time();

        session_write_close();

        audit_log(
            $pdo,
            $uid,
            'LOGIN_PASSWORD_OK_TOTP_REQUIRED',
            'AUTH',
            'INFO',
            'Password verified; TOTP required to complete login'
        );

        unset($user['user_password'], $user['totp_secret_enc'], $user['login_attempts'], $user['account_locked_until']);

        out(200, [
            'ok'            => true,
            'requires_totp' => true,
            'message'       => 'Please enter your two-factor authentication code',
            'user'          => $user,
        ]);
    }

    // No TOTP: full session
    unset($user['user_password'], $user['totp_secret_enc'], $user['login_attempts'], $user['account_locked_until']);

    $_SESSION['user_id']    = $uid;
    $_SESSION['username']   = $user['username'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['logged_in']  = true;
    $_SESSION['login_time'] = time();

    // AUDIT: login success
    audit_log(
        $pdo,
        $uid,
        'LOGIN_SUCCESS',
        'AUTH',
        'INFO',
        'User logged in successfully'
    );

    $shouldSetupTotp = ((int)$user['totp_enabled'] === 0);

    out(200, [
        'ok'                 => true,
        'message'            => 'Login successful',
        'user'               => $user,
        'requires_totp'      => false,
        'suggest_totp_setup' => $shouldSetupTotp,
    ]);
} catch (Throwable $e) {
    error_log('Login error: ' . $e->getMessage());

    // Best-effort audit for server error (no secrets)
    try {
        $pdoTmp = db();
        audit_log(
            $pdoTmp,
            null,
            'LOGIN_ERROR',
            'SERVER',
            'HIGH',
            'Server error during login',
            ['error' => get_class($e)]
        );
    } catch (Throwable $ignored) {
    }

    out(500, ['ok' => false, 'message' => 'Server error occurred. Please try again later.']);
}