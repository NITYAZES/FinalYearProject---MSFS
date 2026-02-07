<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/activity_logger.php';
require_once __DIR__ . '/admin_notification_helper.php';
require_once __DIR__ . '/user_notification_helper.php';

if (!defined('UPLOAD_DIR')) {
    $defaultUploadDir = realpath(__DIR__ . '/../uploads');
    define('UPLOAD_DIR', $defaultUploadDir !== false ? $defaultUploadDir : (__DIR__ . '/../uploads'));
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');

session_start();

/**
 * Prevent browser caching
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Global variable to store data for post-transmission processing
$GLOBALS['download_data'] = null;

function bad(int $code, string $msg): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function logSecurityAudit(PDO $pdo, ?int $userId, string $eventType, string $category, string $severity, string $description, array $metadata = []): void
{
    try {
        $metadata['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $stmt = $pdo->prepare('
            INSERT INTO security_audit_log
              (user_id, event_type, event_category, severity, description, user_agent, metadata_json, created_at)
            VALUES
              (:uid, :etype, :cat, :sev, :descr, :ua, :meta, NOW())
        ');
        $stmt->execute([
            ':uid'   => $userId,
            ':etype' => (string)$eventType,
            ':cat'   => (string)$category,
            ':sev'   => (string)$severity,
            ':descr' => (string)$description,
            ':ua'    => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 512),
            ':meta'  => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        error_log('security_audit_log insert failed: ' . $e->getMessage());
    }
}

function logFileAccess(PDO $pdo, string $fileId, int $userId, string $action, array $metadata = []): void
{
    try {
        $stmt = $pdo->prepare('
            INSERT INTO file_access_log (file_id, user_id, action, ip_address, user_agent, metadata_json, accessed_at)
            VALUES (:file_id, :user_id, :action, :ip, :ua, :metadata, NOW())
        ');
        $stmt->execute([
            ':file_id'  => (string)$fileId,
            ':user_id'  => $userId,
            ':action'   => (string)$action,
            ':ip'       => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ':ua'       => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 512),
            ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        error_log('file_access_log insert failed: ' . $e->getMessage());
    }
}

function formatFileSize(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}

/**
 * Safely encode binary data to base64 with validation
 */
function safeBase64Encode(string $data): string
{
    if ($data === '') {
        throw new Exception('Cannot encode empty data');
    }
    
    $encoded = base64_encode($data);
    
    // Validate the encoding worked
    $decoded = base64_decode($encoded, true);
    if ($decoded === false || $decoded !== $data) {
        throw new Exception('Base64 encoding validation failed');
    }
    
    return $encoded;
}

/**
 * This function runs AFTER the response is sent to the client
 * It increments counters and sends notifications
 */
function incrementDownloadCounter(): void
{
    if (empty($GLOBALS['download_data'])) {
        return;
    }

    $data = $GLOBALS['download_data'];

    // Check if connection was aborted
    if (connection_aborted()) {
        error_log("Download aborted for file ID: {$data['fileId']}. Counter NOT incremented.");
        return;
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $fileId = $data['fileId'];
        $receiverId = $data['receiverId'];
        $currentDecrypt = $data['currentDecrypt'];
        $maxDecrypt = $data['maxDecrypt'];
        $row = $data['row'];

        // 1) Increment decrypt_count
        $upd = $pdo->prepare('
            UPDATE shared_files
            SET decrypt_count = decrypt_count + 1,
                last_accessed_at = NOW()
            WHERE file_id = :file_id AND receiver_id = :receiver_id
        ');
        $upd->execute([
            ':file_id'     => $fileId,
            ':receiver_id' => $receiverId,
        ]);

        $newDecryptCount = $currentDecrypt + 1;

        // 2) Update file_recipients
        $recipientUpd = $pdo->prepare('
            UPDATE file_recipients
            SET access_count = access_count + 1,
                last_accessed_at = NOW()
            WHERE file_id = :file_id AND user_id = :user_id
        ');
        $recipientUpd->execute([
            ':file_id' => $fileId,
            ':user_id' => $receiverId,
        ]);

        $fileSize = (int)$row['file_size'];

        $logMetaArr = [
            'file_id'           => $fileId,
            'file_name'         => $row['file_name'],
            'file_size'         => $fileSize,
            'sender_id'         => (int)$row['sender_id'],
            'decrypt_count'     => $newDecryptCount,
            'max_decrypt_count' => $maxDecrypt,
        ];

        // 3) file_access_log: downloaded
        logFileAccess($pdo, (string)$fileId, $receiverId, 'downloaded', $logMetaArr);

        // 4) security_audit_log: downloaded
        logSecurityAudit(
            $pdo,
            $receiverId,
            'file_downloaded',
            'file',
            'info',
            "Downloaded encrypted file '{$row['file_name']}' (ID: {$fileId})",
            $logMetaArr
        );

        // 5) User activity (receiver)
        $fileSizeFormatted = formatFileSize($fileSize);
        logUserActivity(
            $pdo,
            $receiverId,
            'file_downloaded',
            "Downloaded '{$row['file_name']}' ({$fileSizeFormatted}) from {$row['sender_fullname']}",
            null
        );

        // 6) User activity (sender)
        logUserActivity(
            $pdo,
            (int)$row['sender_id'],
            'file_accessed_by_recipient',
            "'{$row['file_name']}' was downloaded by recipient (" .
                ($maxDecrypt > 0 ? "{$newDecryptCount}/{$maxDecrypt}" : (string)$newDecryptCount) .
                " downloads)",
            null
        );

        // 7) Comprehensive download notifications
        try {
            notifyFileAccessed(
                $pdo,
                (int)$row['sender_id'],
                $receiverId,
                (string)$fileId,
                (string)$row['file_name'],
                $newDecryptCount,
                $maxDecrypt
            );
        } catch (Throwable $e) {
            error_log('notifyFileAccessed error: ' . $e->getMessage());
        }

        // 8) Download-limit notifications
        if ($maxDecrypt > 0) {
            // Warning at 1 remaining
            if ($newDecryptCount === ($maxDecrypt - 1)) {
                try {
                    $remaining = $maxDecrypt - $newDecryptCount;
                    notifyDownloadLimitWarning(
                        $pdo,
                        (int)$row['sender_id'],
                        $receiverId,
                        (string)$fileId,
                        (string)$row['file_name'],
                        $remaining
                    );
                } catch (Throwable $e) {
                    error_log('notifyDownloadLimitWarning error: ' . $e->getMessage());
                }
            }

            // Limit reached
            if ($newDecryptCount >= $maxDecrypt) {
                try {
                    notifyDownloadLimitReached(
                        $pdo,
                        (int)$row['sender_id'],
                        $receiverId,
                        (string)$fileId,
                        (string)$row['file_name']
                    );
                } catch (Throwable $e) {
                    error_log('notifyDownloadLimitReached error: ' . $e->getMessage());
                }

                try {
                    notifyAdminDownloadLimitReached($pdo, [
                        'file_id'       => $fileId,
                        'file_name'     => $row['file_name'],
                        'sender_id'     => (int)$row['sender_id'],
                        'sender_name'   => $row['sender_fullname'] ?? $row['sender_username'],
                        'receiver_id'   => (int)$receiverId,
                        'max_downloads' => (int)$maxDecrypt,
                    ]);

                    error_log("✅ Admin notified: Download limit reached for file '{$row['file_name']}' (ID: {$fileId})");
                } catch (Throwable $e) {
                    error_log('Admin notifyAdminDownloadLimitReached error: ' . $e->getMessage());
                }
            }
        }

        $pdo->commit();
        error_log("✅ Download counter incremented successfully for file ID: {$fileId}");
    } catch (Throwable $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log('Failed to increment download counter: ' . $e->getMessage());
    }
}

// Register shutdown function to run after response is sent
register_shutdown_function('incrementDownloadCounter');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        bad(405, 'Method not allowed');
    }

    // Auth guard
    if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
        bad(401, 'Unauthorized. Please log in.');
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        bad(400, 'No input');
    }

    $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $fileId = trim((string)($input['fileId'] ?? ''));

    if ($fileId === '') {
        bad(422, 'fileId is required');
    }

    $receiverId = (int)$_SESSION['user_id'];
    $pdo = db();

    // Look up file (must belong to receiver)
    $stmt = $pdo->prepare('
        SELECT
            sf.file_id,
            sf.sender_id,
            sf.receiver_id,
            sf.file_name,
            sf.file_size,
            sf.mime_type,
            sf.storage_path,
            sf.enc_file_key,
            sf.hash_enc,
            sf.policy_json,
            sf.expiry_time,
            sf.max_decrypt_count,
            sf.decrypt_count,
            sf.uploaded_at,
            sf.status,
            em.encryption_score,
            em.encryption_rating,
            em.encryption_percentage,
            em.score_breakdown_json,
            s.username        AS sender_username,
            s.user_fullname   AS sender_fullname,
            ck.public_key_jwk AS sender_public_key_jwk
        FROM shared_files sf
        JOIN users s ON sf.sender_id = s.user_id
        LEFT JOIN user_crypto_keys ck ON s.user_id = ck.user_id AND ck.key_status = "active"
        LEFT JOIN encryption_metrics em ON sf.file_id = em.file_id AND sf.receiver_id = em.receiver_id
        WHERE sf.file_id = :file_id
          AND sf.receiver_id = :receiver_id
        LIMIT 1
    ');
    $stmt->execute([
        ':file_id'     => $fileId,
        ':receiver_id' => $receiverId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        logFileAccess($pdo, $fileId, $receiverId, 'access_denied', [
            'reason' => 'file_not_found_or_no_access',
        ]);

        logUserActivity(
            $pdo,
            $receiverId,
            'file_access_denied',
            "Attempted to access non-existent or unauthorized file (ID: {$fileId})",
            null
        );

        logSecurityAudit(
            $pdo,
            $receiverId,
            'file_access_denied',
            'file',
            'warning',
            "Access denied for file (ID: {$fileId})",
            ['file_id' => $fileId, 'reason' => 'file_not_found_or_no_access']
        );

        try {
            notifyAccessDenied(
                $pdo,
                $receiverId,
                (string)$fileId,
                '(unknown)',
                'file_not_found_or_no_access'
            );
        } catch (Throwable $e) {
            error_log('notifyAccessDenied error: ' . $e->getMessage());
        }

        bad(404, 'File not found or you do not have access to it.');
    }

    // Decode encryption metrics
    $encMetrics = null;
    if (!empty($row['score_breakdown_json'])) {
        $tmp = json_decode((string)$row['score_breakdown_json'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
            $encMetrics = [
                'score'      => (int)$row['encryption_score'],
                'rating'     => $row['encryption_rating'],
                'percentage' => (float)$row['encryption_percentage'],
                'breakdown'  => $tmp,
            ];
        }
    }

    // Policy checks
    $maxDecrypt     = (int)($row['max_decrypt_count'] ?? 0);
    $currentDecrypt = (int)($row['decrypt_count'] ?? 0);

    // 1) Expiry check
    if (!empty($row['expiry_time'])) {
        $expiryTs = strtotime((string)$row['expiry_time']);
        if ($expiryTs !== false && $expiryTs <= time()) {
            logFileAccess($pdo, (string)$row['file_id'], $receiverId, 'expired', [
                'file_name'   => $row['file_name'],
                'expiry_time' => $row['expiry_time'],
            ]);

            logUserActivity(
                $pdo,
                $receiverId,
                'file_access_expired',
                "Attempted to download expired file '{$row['file_name']}'",
                null
            );

            logSecurityAudit(
                $pdo,
                $receiverId,
                'file_access_expired',
                'file',
                'warning',
                "Attempted to access expired file '{$row['file_name']}' (ID: {$fileId})",
                ['file_id' => $fileId, 'expiry_time' => $row['expiry_time']]
            );

            try {
                notifyFileExpired(
                    $pdo,
                    (int)$row['sender_id'],
                    $receiverId,
                    (string)$row['file_id'],
                    (string)$row['file_name']
                );
            } catch (Throwable $e) {
                error_log('notifyExpiredAccess error: ' . $e->getMessage());
            }

            bad(410, 'This file has expired and is no longer accessible.');
        }
    }

    // 2) Max download check
    if ($maxDecrypt > 0 && $currentDecrypt >= $maxDecrypt) {
        logFileAccess($pdo, (string)$row['file_id'], $receiverId, 'max_downloads_reached', [
            'file_name'         => $row['file_name'],
            'decrypt_count'     => $currentDecrypt,
            'max_decrypt_count' => $maxDecrypt,
        ]);

        logUserActivity(
            $pdo,
            $receiverId,
            'file_download_limit_reached',
            "Attempted to download '{$row['file_name']}' (limit: {$maxDecrypt} reached)",
            null
        );

        logSecurityAudit(
            $pdo,
            $receiverId,
            'file_max_downloads_reached',
            'file',
            'warning',
            "Max downloads reached for file '{$row['file_name']}' (ID: {$fileId})",
            ['file_id' => $fileId, 'decrypt_count' => $currentDecrypt, 'max_decrypt_count' => $maxDecrypt]
        );

        try {
            notifyDownloadLimitReached(
                $pdo,
                (int)$row['sender_id'],
                $receiverId,
                (string)$row['file_id'],
                (string)$row['file_name']
            );
        } catch (Throwable $e) {
            error_log('notifyLimitExceeded error: ' . $e->getMessage());
        }

        bad(403, "Download limit reached. This file can only be downloaded {$maxDecrypt} time(s).");
    }

    $storagePathRaw = (string)($row['storage_path'] ?? '');
    $isAbsolute = ($storagePathRaw !== '' && (
        $storagePathRaw[0] === '/' || preg_match('~^[A-Za-z]:\\\\~', $storagePathRaw) === 1
    ));

    $storageFullPath = $isAbsolute
        ? $storagePathRaw
        : rtrim(UPLOAD_DIR, '/') . '/' . ltrim($storagePathRaw, '/');

    if (!file_exists($storageFullPath) || !is_readable($storageFullPath)) {
        logFileAccess($pdo, (string)$row['file_id'], $receiverId, 'file_not_found_on_disk', [
            'storage_path' => $row['storage_path'],
        ]);

        logSecurityAudit(
            $pdo,
            $receiverId,
            'file_not_found_on_disk',
            'file',
            'error',
            "Encrypted file not found on disk for '{$row['file_name']}' (ID: {$fileId})",
            ['file_id' => $fileId, 'storage_path' => $row['storage_path']]
        );

        bad(500, 'Failed to read encrypted file.');
    }

    $encData = file_get_contents($storageFullPath);
    if ($encData === false) {
        logFileAccess($pdo, (string)$row['file_id'], $receiverId, 'read_error', [
            'storage_path' => $row['storage_path'],
        ]);

        logSecurityAudit(
            $pdo,
            $receiverId,
            'file_read_error',
            'file',
            'error',
            "Failed to read encrypted file '{$row['file_name']}' (ID: {$fileId})",
            ['file_id' => $fileId, 'storage_path' => $row['storage_path']]
        );

        bad(500, 'Failed to read encrypted file.');
    }

    // Validate we got data
    if (strlen($encData) === 0) {
        error_log("ERROR: Encrypted file is empty for file ID: {$fileId}");
        bad(500, 'Encrypted file is empty.');
    }

    // CRITICAL: Safely encode to base64 with validation
    try {
        $encryptedDataB64 = safeBase64Encode($encData);
        
        // Log for debugging
        error_log("File {$fileId}: Raw size: " . strlen($encData) . " bytes, Base64 size: " . strlen($encryptedDataB64) . " chars");
        
    } catch (Exception $e) {
        error_log("Base64 encoding failed for file {$fileId}: " . $e->getMessage());
        bad(500, 'Failed to encode encrypted file.');
    }

    // Decode policy
    $policy = [];
    if (!empty($row['policy_json'])) {
        $decoded = json_decode((string)$row['policy_json'], true);
        if (is_array($decoded)) {
            $policy = $decoded;
        }
    }

    // Store data for post-transmission processing
    $GLOBALS['download_data'] = [
        'fileId'         => $fileId,
        'receiverId'     => $receiverId,
        'currentDecrypt' => $currentDecrypt,
        'maxDecrypt'     => $maxDecrypt,
        'row'            => $row,
    ];

    // Remaining decrypts
    $remainingDecrypts = null;
    if ($maxDecrypt > 0) {
        $remainingDecrypts = max(0, $maxDecrypt - $currentDecrypt - 1);
    }

    // Build sender public key JWK
    $senderPubKey = null;
    if (!empty($row['sender_public_key_jwk'])) {
        $tmp = json_decode((string)$row['sender_public_key_jwk'], true);
        if (is_array($tmp)) {
            $senderPubKey = $tmp;
        }
    }

    $senderFullName = (string)($row['sender_fullname'] ?? '');
    $senderUsername = (string)($row['sender_username'] ?? '');
    $displayName = $senderFullName !== '' ? $senderFullName : $senderUsername;

    // Validate enc_file_key and hash_enc are not empty
    if (empty($row['enc_file_key']) || empty($row['hash_enc'])) {
        error_log("ERROR: Missing enc_file_key or hash_enc for file {$fileId}");
        bad(500, 'Encryption keys are missing for this file.');
    }

    // Send response - counter will be incremented AFTER this is successfully sent
    echo json_encode([
        'ok'   => true,
        'file' => [
            'fileId'            => $row['file_id'],
            'fileName'          => $row['file_name'],
            'mimeType'          => $row['mime_type'],
            'fileSize'          => (int)$row['file_size'],
            'uploadedAt'        => $row['uploaded_at'],
            'expiryTime'        => $row['expiry_time'],
            'decryptCount'      => $currentDecrypt + 1,
            'maxDecryptCount'   => $maxDecrypt,
            'remainingDecrypts' => $remainingDecrypts,

            'encryptedData' => $encryptedDataB64,
            'encFileKey'    => $row['enc_file_key'],
            'hashEnc'       => $row['hash_enc'],
            'policy'        => $policy,

            'encryptionMetrics' => $encMetrics,

            'sender' => [
                'username'  => $displayName,
                'fullName'  => $displayName,
                'publicKey' => $senderPubKey,
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Flush output to ensure data is sent
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        ob_end_flush();
        flush();
    }

} catch (Throwable $e) {
    error_log('Download error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    $GLOBALS['download_data'] = null;
    bad(500, 'Server error occurred. Please try again later.');
}