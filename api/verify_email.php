<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin_notification_helper.php';
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');


function out(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function bad(int $code, string $msg): void
{
    out($code, ['ok' => false, 'message' => $msg]);
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

/* -------------------- Security audit logger -------------------- */
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
            ':etype' => (string)$eventType,
            ':cat'   => (string)$category,
            ':sev'   => (string)$severity,
            ':descr' => (string)$description,
            ':ua'    => get_user_agent(),
            ':meta'  => $metaJson,
        ]);
    } catch (Throwable $e) {
        // Never break main flow due to audit logging failure
        error_log('Audit log insert failed: ' . $e->getMessage());
    }
}

try {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        out(400, ['ok' => false, 'message' => 'No input']);
    }

    // Optional: small DoS protection
    if (strlen($raw) > 100_000) {
        out(413, ['ok' => false, 'message' => 'Payload too large']);
    }

    $in = json_decode($raw, true);
    if (!is_array($in)) {
        out(400, ['ok' => false, 'message' => 'Invalid JSON']);
    }

    $email = strtolower(trim((string)($in['email'] ?? '')));
    $code  = trim((string)($in['code'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        out(422, ['ok' => false, 'message' => 'Valid email required']);
    }

    if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
        out(422, ['ok' => false, 'message' => 'Valid 6-digit code required']);
    }

    $pdo = db();
    $pdo->beginTransaction();

    // Find user
    $st = $pdo->prepare(
        'SELECT user_id, username, status, email_verified_at
         FROM users
         WHERE user_email = :e
         LIMIT 1'
    );
    $st->execute([':e' => $email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // AUDIT: possible enumeration (email not found)
        audit_log(
            $pdo,
            null,
            'POSSIBLE_ENUMERATION',
            'ENUMERATION',
            'MEDIUM',
            'Email verification attempt for non-existent account',
            ['email' => $email]
        );

        $pdo->rollBack();
        out(404, ['ok' => false, 'message' => 'Account not found']);
    }

    $uid = (int)$user['user_id'];

    // Latest active code (not consumed)
    $st = $pdo->prepare(
        'SELECT id, code_hash, expires_at
         FROM email_verification_codes
         WHERE user_id = :uid AND consumed_at IS NULL
         ORDER BY id DESC
         LIMIT 1'
    );
    $st->execute([':uid' => $uid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Not in your requested list, but still useful (optional)
        audit_log(
            $pdo,
            $uid,
            'EMAIL_VERIFICATION_FAILED',
            'AUTH',
            'MEDIUM',
            'No active verification code found for user',
            ['email' => $email]
        );

        $pdo->rollBack();
        out(422, ['ok' => false, 'message' => 'No active code. Request a new one.']);
    }

    // Expired OTP
    if (new DateTimeImmutable($row['expires_at']) < new DateTimeImmutable('now')) {
        audit_log(
            $pdo,
            $uid,
            'EMAIL_VERIFICATION_EXPIRED',
            'AUTH',
            'MEDIUM',
            'Email verification OTP expired',
            ['expires_at' => $row['expires_at']]
        );

        $pdo->rollBack();
        out(422, ['ok' => false, 'message' => 'Code expired. Request a new one.']);
    }

    // Wrong OTP
    if (!password_verify($code, (string)$row['code_hash'])) {
        audit_log(
            $pdo,
            $uid,
            'EMAIL_VERIFICATION_FAILED',
            'AUTH',
            'MEDIUM',
            'Invalid email verification OTP submitted'
        );

        $pdo->rollBack();
        out(401, ['ok' => false, 'message' => 'Invalid code']);
    }

    // Mark consumed + activate user
    $pdo->prepare(
        'UPDATE email_verification_codes
         SET consumed_at = NOW()
         WHERE id = :id'
    )->execute([':id' => $row['id']]);

    $pdo->prepare(
        'UPDATE users
         SET email_verified_at = NOW(), status = "active"
         WHERE user_id = :uid'
    )->execute([':uid' => $uid]);

    $pdo->commit();

    // Success audit (optional but good)
    audit_log(
        $pdo,
        $uid,
        'EMAIL_VERIFICATION_SUCCESS',
        'AUTH',
        'INFO',
        'User email verified successfully',
        ['email' => $email]
    );

    // Notify admins about email verification
    try {
        notifyAdminEmailVerified($pdo, $uid, (string)$user['username'], $email);
    } catch (Throwable $notifErr) {
        error_log('Failed to send email verification notification: ' . $notifErr->getMessage());
    }

    out(200, [
        'ok'      => true,
        'message' => 'Email verified. You can now sign in.',
    ]);
} catch (Throwable $e) {
    if ($pdo ?? null) {
        try {
            $pdo->rollBack();
        } catch (Throwable $ignored) {
        }
    }

    // Best-effort audit for server error
    try {
        $pdoTmp = db();
        audit_log(
            $pdoTmp,
            null,
            'EMAIL_VERIFICATION_FAILED',
            'SERVER',
            'HIGH',
            'Server error during email verification',
            ['error' => get_class($e)]
        );
    } catch (Throwable $ignored) {
    }

    error_log('verify_email error: ' . $e->getMessage());
    out(500, ['ok' => false, 'message' => 'Server error']);
}