<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/activity_logger.php';

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

function bad(int $code, string $msg): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

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

    // Look up file (must belong to receiver) - NO DOWNLOAD INCREMENT
    $stmt = $pdo->prepare('
        SELECT
            sf.file_id,
            sf.sender_id,
            sf.receiver_id,
            sf.file_name,
            sf.file_size,
            sf.mime_type,
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
            s.user_fullname   AS sender_fullname
        FROM shared_files sf
        JOIN users s ON sf.sender_id = s.user_id
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
            bad(410, 'This file has expired and is no longer accessible.');
        }
    }

    // 2) Max download check
    if ($maxDecrypt > 0 && $currentDecrypt >= $maxDecrypt) {
        bad(403, "Download limit reached. This file can only be downloaded {$maxDecrypt} time(s).");
    }

    // Remaining decrypts (based on current count - NOT incremented yet)
    $remainingDecrypts = null;
    if ($maxDecrypt > 0) {
        $remainingDecrypts = max(0, $maxDecrypt - $currentDecrypt);
    }

    $senderFullName = (string)($row['sender_fullname'] ?? '');
    $senderUsername = (string)($row['sender_username'] ?? '');
    $displayName = $senderFullName !== '' ? $senderFullName : $senderUsername;

    // Decode policy
    $policy = [];
    if (!empty($row['policy_json'] ?? '')) {
        $decoded = json_decode((string)$row['policy_json'], true);
        if (is_array($decoded)) {
            $policy = $decoded;
        }
    }

    // Send response - METADATA ONLY, no encrypted data, no counter increment
    echo json_encode([
        'ok'   => true,
        'file' => [
            'fileId'            => $row['file_id'],
            'fileName'          => $row['file_name'],
            'mimeType'          => $row['mime_type'],
            'fileSize'          => (int)$row['file_size'],
            'uploadedAt'        => $row['uploaded_at'],
            'expiryTime'        => $row['expiry_time'],
            'decryptCount'      => $currentDecrypt, // Current count, not incremented
            'maxDecryptCount'   => $maxDecrypt,
            'remainingDecrypts' => $remainingDecrypts,

            'encryptionMetrics' => $encMetrics,
            'policy'            => $policy,

            'sender' => [
                'username'  => $displayName,
                'fullName'  => $displayName,
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Get file info error: ' . $e->getMessage());
    bad(500, 'Server error occurred. Please try again later.');
}