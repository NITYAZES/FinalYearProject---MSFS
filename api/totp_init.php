<?php

declare(strict_types=1);

session_start();

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/totp_helper.php';
// ✅ NEW: user notifications
require_once __DIR__ . '/user_notification_helper.php';

// Allow POST requests
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(405, ['ok' => false, 'message' => 'Method not allowed']);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    json_out(401, ['ok' => false, 'message' => 'Not authenticated']);
}

try {
    $pdo = db();

    // First, let's check what columns exist in the users table
    $columns = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
    error_log("Users table columns: " . implode(', ', $columns));

    // Build query based on available columns
    $selectFields = ['user_id', 'username', 'user_email'];
    if (in_array('totp_enabled', $columns, true)) {
        $selectFields[] = 'totp_enabled';
    }

    $sql = 'SELECT ' . implode(', ', $selectFields) . ' FROM users WHERE user_id = :id LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        json_out(404, ['ok' => false, 'message' => 'User not found']);
    }

    // Check if 2FA is already enabled (if column exists)
    if (isset($user['totp_enabled']) && (int)$user['totp_enabled'] === 1) {
        json_out(200, [
            'ok'      => false,
            'message' => 'Two-factor authentication is already enabled'
        ]);
    }

    // Alternative: Check user_mfa_totp table if it exists
    try {
        $mfaCheck = $pdo->prepare('SELECT is_enabled FROM user_mfa_totp WHERE user_id = :id LIMIT 1');
        $mfaCheck->execute([':id' => (int)$_SESSION['user_id']]);
        $mfaData = $mfaCheck->fetch(PDO::FETCH_ASSOC);

        if ($mfaData && (int)($mfaData['is_enabled'] ?? 0) === 1) {
            json_out(200, [
                'ok'      => false,
                'message' => 'Two-factor authentication is already enabled'
            ]);
        }
    } catch (PDOException $e) {
        // Table doesn't exist, that's okay
        error_log('user_mfa_totp table check: ' . $e->getMessage());
    }

    // ✅ Generate new secret and backup codes
    $secret      = generateTotpSecret();
    $backupCodes = generateBackupCodes();

    // ✅ Store temporarily in session
    $_SESSION['temp_totp_secret']  = $secret;
    $_SESSION['temp_backup_codes'] = $backupCodes;

    // Username label (fallback chain)
    $usernameLabel = $user['username'] ?? $user['user_email'] ?? ('user-' . $user['user_id']);

    // App name (issuer)
    $issuer = 'Multimedia Secure File Share';

    // Build the otpauth URI
    $otpauthUri = getTotpUri($secret, $usernameLabel, $issuer);

    // Build QR code URL
    $qrImageUrl = getTotpQrCodeUrl($secret, $usernameLabel, $issuer);

    // Format secret for display (groups of 4)
    $secretFormatted = implode(' ', str_split($secret, 4));

    // Log success (never log full secret)
    error_log("TOTP Init SUCCESS - User: {$user['user_id']}, Secret: " . substr($secret, 0, 8) . "...");

    json_out(200, [
        'ok'               => true,
        'secret'           => $secret,
        'secret_formatted' => $secretFormatted,
        'qr_code_url'      => $qrImageUrl,
        'otpauth_url'      => $otpauthUri,
        'backup_codes'     => $backupCodes,
    ]);
} catch (Throwable $e) {
    error_log('TOTP init error: ' . $e->getMessage());
    error_log('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
    error_log('Trace: ' . $e->getTraceAsString());

    json_out(500, [
        'ok'      => false,
        'message' => 'Failed to initialize 2FA setup',
        'error'   => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine()
    ]);
}
