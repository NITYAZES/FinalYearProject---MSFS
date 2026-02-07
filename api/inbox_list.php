<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');

session_start();

/**
 * Prevent browser caching (blocks back/forward after logout)
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

function bad(int $code, string $msg): never
{
    http_response_code($code);
    echo json_encode([
        'ok'      => false,
        'message' => $msg,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Matches your table schema:
 * security_audit_log(user_id,event_type,event_category,severity,description,user_agent,metadata_json,created_at)
 * Note: IP goes into metadata_json (your screenshot has no ip_address column).
 */
function logSecurityEvent(PDO $pdo, ?int $userId, string $eventType, string $severity, string $description, array $meta = []): void
{
    try {
        $meta['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $stmt = $pdo->prepare('
            INSERT INTO security_audit_log
              (user_id, event_type, event_category, severity, description, user_agent, metadata_json, created_at)
            VALUES
              (:uid, :etype, "file", :sev, :descr, :ua, :meta, NOW())
        ');

        $stmt->execute([
            ':uid'   => $userId,
            ':etype' => $eventType,
            ':sev'   => $severity,
            ':descr' => $description,
            ':ua'    => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 512),
            ':meta'  => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        // Auditing must never break the main feature
        error_log('security_audit_log insert failed: ' . $e->getMessage());
    }
}

try {
    // Only allow GET
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        bad(405, 'Method not allowed');
    }

    // Must be logged in
    if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
        bad(401, 'Unauthorized. Please log in.');
    }

    $pdo = db();
    $receiverId = (int)$_SESSION['user_id'];

    // ✅ Important: filter out rows that are "active" but effectively expired by time or max downloads
    $sql = "
        SELECT
            sf.file_id           AS file_id,
            sf.sender_id         AS sender_id,
            sf.file_name         AS filename,
            sf.file_size         AS size_bytes,
            sf.mime_type         AS mime_type,
            sf.uploaded_at       AS created_at,
            sf.expiry_time       AS expires_at,
            sf.max_decrypt_count AS max_decrypt_count,
            sf.decrypt_count     AS decrypt_count,
            u.username           AS sender_username,
            u.user_fullname      AS sender_fullname
        FROM shared_files sf
        LEFT JOIN users u ON sf.sender_id = u.user_id
        WHERE sf.receiver_id = :rid
          AND sf.status = 'active'
          AND (sf.expiry_time IS NULL OR sf.expiry_time > NOW())
          AND (sf.max_decrypt_count IS NULL OR sf.decrypt_count < sf.max_decrypt_count)
        ORDER BY sf.uploaded_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':rid' => $receiverId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $files = [];

    foreach ($rows as $row) {
        $max  = $row['max_decrypt_count'];
        $used = $row['decrypt_count'] ?? 0;

        $remaining = null;
        if ($max !== null) {
            $remaining = max(0, (int)$max - (int)$used);
        }

        $senderFullName = $row['sender_fullname'] ?? '';
        $senderUserId   = $row['sender_username'] ?? '';

        $displayUsername = $senderFullName !== '' ? $senderFullName : $senderUserId;
        if ($displayUsername === '') {
            $displayUsername = 'User #' . (int)$row['sender_id'];
        }

        $files[] = [
            'fileId'            => (string)$row['file_id'],
            'filename'          => $row['filename'],
            'sizeBytes'         => (int)$row['size_bytes'],
            'mimeType'          => $row['mime_type'],
            'createdAt'         => $row['created_at'],
            'expiresAt'         => $row['expires_at'],
            'maxDecryptCount'   => $max !== null ? (int)$max : null,
            'decryptCount'      => (int)$used,
            'remainingDecrypts' => $remaining,
            'sender'            => [
                'username' => $displayUsername,
                'fullName' => $displayUsername,
            ],
        ];
    }

    // ✅ Audit (optional but good): inbox viewed
    logSecurityEvent(
        $pdo,
        $receiverId,
        'INBOX_LIST_VIEWED',
        'info',
        'User viewed inbox list',
        [
            'count' => count($files),
        ]
    );

    echo json_encode([
        'ok'    => true,
        'files' => $files,
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    error_log('inbox_list error: ' . $e->getMessage());
    bad(500, 'Server error occurred. Please try again later.');
}
