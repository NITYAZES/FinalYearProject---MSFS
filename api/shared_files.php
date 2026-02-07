<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/user_notification_helper.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent browser back/forward cache after logout
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Always JSON
header('Content-Type: application/json; charset=utf-8');

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

/**
 * Audit logger matches your schema:
 * security_audit_log(user_id,event_type,event_category,severity,description,user_agent,metadata_json,created_at)
 */
function logFileAudit(
    PDO $pdo,
    int $actorUserId,
    string $eventType,
    string $description,
    array $metadata = [],
    string $severity = 'info'
): void {
    try {
        $metadata = array_merge(['ip' => get_client_ip()], $metadata);

        $metaJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($metaJson === false) $metaJson = '{}';

        $stmt = $pdo->prepare("
            INSERT INTO security_audit_log
                (user_id, event_type, event_category, severity, description, user_agent, metadata_json, created_at)
            VALUES
                (:uid, :etype, 'file', :sev, :descr, :ua, :meta, NOW())
        ");

        $stmt->execute([
            ':uid'   => $actorUserId,
            ':etype' => $eventType,
            ':sev'   => $severity,
            ':descr' => $description,
            ':ua'    => get_user_agent(),
            ':meta'  => $metaJson,
        ]);
    } catch (Throwable $e) {
        error_log("❌ file audit insert failed: " . $e->getMessage());
    }
}

/**
 * Log unauthorized / rejected file actions (useful for abuse detection)
 */
function logUnauthorizedFileAction(PDO $pdo, int $actorUserId, string $eventType, string $message, array $metadata = []): void
{
    logFileAudit(
        $pdo,
        $actorUserId,
        $eventType,
        $message,
        $metadata,
        'warning'
    );
}

/* -------------------- Auth check -------------------- */
if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    out(401, [
        'success' => false,
        'message' => 'Unauthorized. Please login.'
    ]);
}

$user_id = (int)$_SESSION['user_id'];

/* -------------------- DB (use shared db() from config.php) -------------------- */
try {
    $pdo = db();
} catch (Throwable $e) {
    error_log("Database connection error: " . $e->getMessage());
    out(500, [
        'success' => false,
        'message' => 'Database connection failed'
    ]);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string)($_GET['action'] ?? '');

try {
    switch ($action) {
        case 'get_shared_files':
            getSharedFiles($pdo, $user_id);
            break;

        case 'edit_policy':
            if ($method !== 'POST') throw new Exception('POST method required');
            $data = json_decode(file_get_contents('php://input') ?: '[]', true);
            editPolicy($pdo, $user_id, is_array($data) ? $data : []);
            break;

        case 'remove_recipient':
            if ($method !== 'POST') throw new Exception('POST method required');
            $data = json_decode(file_get_contents('php://input') ?: '[]', true);
            removeRecipient($pdo, $user_id, is_array($data) ? $data : []);
            break;

        case 'revoke_share':
            if ($method !== 'POST') throw new Exception('POST method required');
            $data = json_decode(file_get_contents('php://input') ?: '[]', true);
            revokeShare($pdo, $user_id, is_array($data) ? $data : []);
            break;

        case 'delete_file':
            if ($method !== 'POST') throw new Exception('POST method required');
            $data = json_decode(file_get_contents('php://input') ?: '[]', true);
            deleteFile($pdo, $user_id, is_array($data) ? $data : []);
            break;

        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Throwable $e) {
    error_log("Error in shared_files.php: " . $e->getMessage());
    out(400, [
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/* -------------------- Actions -------------------- */

function getSharedFiles(PDO $pdo, int $userId): void
{
    $query = "
        SELECT 
            sf.file_id,
            sf.file_name,
            sf.file_size,
            sf.mime_type,
            sf.uploaded_at,
            sf.expiry_time,
            sf.max_decrypt_count,
            sf.decrypt_count,
            sf.status,
            em.encryption_score,
            em.encryption_rating,
            em.encryption_time_ms,
            em.size_overhead_percent,
            em.score_breakdown_json as encryption_metrics_json,
            u.username as receiver_username,
            u.user_email as receiver_email,
            u.user_fullname as receiver_name
        FROM shared_files sf
        JOIN users u ON sf.receiver_id = u.user_id
        LEFT JOIN encryption_metrics em ON sf.file_id = em.file_id AND sf.receiver_id = em.receiver_id
        WHERE sf.sender_id = :user_id
        ORDER BY sf.uploaded_at DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $userId]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groupedFiles = [];
    foreach ($files as $file) {
        $fileId = (string)$file['file_id'];

        if (!isset($groupedFiles[$fileId])) {
            $groupedFiles[$fileId] = [
                'file_id' => $file['file_id'],
                'file_name' => $file['file_name'],
                'file_size' => $file['file_size'],
                'mime_type' => $file['mime_type'],
                'uploaded_at' => $file['uploaded_at'],
                'expiry_time' => $file['expiry_time'],
                'max_decrypt_count' => $file['max_decrypt_count'],
                'decrypt_count' => $file['decrypt_count'],
                'status' => $file['status'],
                'encryption_score' => $file['encryption_score'],
                'encryption_rating' => $file['encryption_rating'],
                'encryption_time_ms' => $file['encryption_time_ms'],
                'size_overhead_percent' => $file['size_overhead_percent'],
                'encryption_metrics_json' => $file['encryption_metrics_json'],
                'recipients' => [],
                'recipient_count' => 0,
                'total_views' => 0,
                'active_recipients' => 0
            ];
        }

        $groupedFiles[$fileId]['recipients'][] = [
            'username' => $file['receiver_username'],
            'email' => $file['receiver_email'],
            'name' => $file['receiver_name']
        ];

        $groupedFiles[$fileId]['recipient_count']++;
        if ($file['status'] === 'active') {
            $groupedFiles[$fileId]['active_recipients']++;
        }
    }

    $filesArray = array_values($groupedFiles);

    $totalShared = count($filesArray);
    $totalRecipients = 0;
    $activeShares = 0;

    foreach ($filesArray as $f) {
        $totalRecipients += (int)$f['recipient_count'];
        if ($f['status'] === 'active' && !empty($f['expiry_time']) && strtotime((string)$f['expiry_time']) > time()) {
            $activeShares++;
        }
    }

    $stats = [
        'total_shared' => $totalShared,
        'total_recipients' => $totalRecipients,
        'total_views' => 0,
        'active_shares' => $activeShares
    ];

    out(200, [
        'success' => true,
        'files' => $filesArray,
        'stats' => $stats
    ]);
}

function editPolicy(PDO $pdo, int $userId, array $data): void
{
    $fileId = (string)($data['file_id'] ?? '');
    $expiryTime = (string)($data['expiry_time'] ?? '');
    $maxDecryptCount = $data['max_decrypt_count'] ?? '';

    if ($fileId === '' || $expiryTime === '' || $maxDecryptCount === '') {
        throw new Exception('File ID, expiry time, and max downloads are required');
    }

    $expiryTimestamp = strtotime($expiryTime);
    if ($expiryTimestamp === false) throw new Exception('Invalid expiry time format');
    if ($expiryTimestamp <= time()) throw new Exception('Expiry time must be in the future');

    $maxDecryptCount = (int)$maxDecryptCount;
    if ($maxDecryptCount < 1) throw new Exception('Maximum downloads must be at least 1');

    // Get old policy info
    $oldStmt = $pdo->prepare("
        SELECT file_name, expiry_time, max_decrypt_count, policy_json
        FROM shared_files
        WHERE file_id = :file_id AND sender_id = :sender_id
        LIMIT 1
    ");
    $oldStmt->execute([':file_id' => $fileId, ':sender_id' => $userId]);
    $old = $oldStmt->fetch(PDO::FETCH_ASSOC);

    if (!$old) {
        logUnauthorizedFileAction($pdo, $userId, 'UNAUTHORIZED_FILE_POLICY_EDIT', 'Policy edit denied: not owner or file not found', [
            'file_id' => $fileId
        ]);
        throw new Exception('File not found or you do not have permission');
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM shared_files WHERE file_id = :file_id AND sender_id = :sender_id");
    $countStmt->execute([':file_id' => $fileId, ':sender_id' => $userId]);
    $recipientsAffected = (int)$countStmt->fetchColumn();

    $expiryDb = date('Y-m-d H:i:s', $expiryTimestamp);

    // Update policy
    $updateQuery = "
        UPDATE shared_files 
        SET expiry_time = :expiry_time_db, 
            max_decrypt_count = :max_dc_db,
            policy_json = JSON_SET(
                COALESCE(policy_json, '{}'),
                '$.expiry_time', :expiry_time_json,
                '$.max_decrypt_count', :max_dc_json
            )
        WHERE file_id = :file_id
          AND sender_id = :sender_id
    ";

    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->execute([
        ':expiry_time_db'   => $expiryDb,
        ':max_dc_db'        => $maxDecryptCount,
        ':expiry_time_json' => $expiryDb,
        ':max_dc_json'      => $maxDecryptCount,
        ':file_id'          => $fileId,
        ':sender_id'        => $userId
    ]);

    $affectedRows = $updateStmt->rowCount();

    // Get all affected receiver IDs for notifications
    $receiversStmt = $pdo->prepare('
        SELECT receiver_id FROM shared_files WHERE file_id = :file_id
    ');
    $receiversStmt->execute([':file_id' => $fileId]);
    $receiverIds = $receiversStmt->fetchAll(PDO::FETCH_COLUMN);

    // Notify about policy change
    notifyPolicyEdited(
        $pdo,
        $userId,
        array_map('intval', $receiverIds),
        $fileId,
        $old['file_name']
    );

    // Audit log
    logFileAudit(
        $pdo,
        $userId,
        'FILE_POLICY_UPDATED',
        "User updated policy for file '{$old['file_name']}' (ID: {$fileId})",
        [
            'file_id' => $fileId,
            'file_name' => $old['file_name'],
            'recipients_affected' => $recipientsAffected,
            'changes' => [
                'expiry_time' => ['old' => $old['expiry_time'], 'new' => $expiryDb],
                'max_decrypt_count' => ['old' => (int)$old['max_decrypt_count'], 'new' => $maxDecryptCount],
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'info'
    );

    out(200, [
        'success' => true,
        'message' => "Policy updated for all recipients ({$affectedRows} recipients affected)"
    ]);
}

function removeRecipient(PDO $pdo, int $userId, array $data): void
{
    $fileId = (string)($data['file_id'] ?? '');
    $recipientEmail = (string)($data['recipient_email'] ?? '');

    if ($fileId === '' || $recipientEmail === '') {
        throw new Exception('File ID and recipient email are required');
    }

    // Get recipient info
    $userStmt = $pdo->prepare("SELECT user_id, username, user_email FROM users WHERE user_email = :email LIMIT 1");
    $userStmt->execute([':email' => $recipientEmail]);
    $recipient = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipient) {
        logUnauthorizedFileAction($pdo, $userId, 'REMOVE_RECIPIENT_RECIPIENT_NOT_FOUND', 'Remove recipient failed: recipient not found', [
            'file_id' => $fileId,
            'recipient_email' => $recipientEmail
        ]);
        throw new Exception('Recipient not found');
    }

    // Get file info
    $fileStmt = $pdo->prepare("SELECT file_name FROM shared_files WHERE file_id = :file_id AND sender_id = :sender_id LIMIT 1");
    $fileStmt->execute([':file_id' => $fileId, ':sender_id' => $userId]);
    $fileRow = $fileStmt->fetch(PDO::FETCH_ASSOC);

    if (!$fileRow) {
        logUnauthorizedFileAction($pdo, $userId, 'UNAUTHORIZED_REMOVE_RECIPIENT', 'Remove recipient denied: not owner or file not found', [
            'file_id' => $fileId,
            'recipient_user_id' => (int)$recipient['user_id']
        ]);
        throw new Exception('File not found or you do not have permission');
    }

    // Delete the share
    $deleteStmt = $pdo->prepare("
        DELETE FROM shared_files
        WHERE file_id = :file_id AND sender_id = :sender_id AND receiver_id = :receiver_id
    ");
    $deleteStmt->execute([
        ':file_id' => $fileId,
        ':sender_id' => $userId,
        ':receiver_id' => (int)$recipient['user_id']
    ]);

    if ($deleteStmt->rowCount() === 0) {
        logUnauthorizedFileAction($pdo, $userId, 'REMOVE_RECIPIENT_NOT_FOUND', 'Remove recipient failed: share row not found', [
            'file_id' => $fileId,
            'recipient_user_id' => (int)$recipient['user_id']
        ]);
        throw new Exception('Recipient not found or you do not have permission');
    }

    // Notify about removal
    notifyRecipientRemoved(
        $pdo,
        $userId,
        (int)$recipient['user_id'],
        $fileId,
        $fileRow['file_name']
    );

    // Audit log
    logFileAudit(
        $pdo,
        $userId,
        'FILE_RECIPIENT_REMOVED',
        "User removed recipient '{$recipient['user_email']}' from file '{$fileRow['file_name']}' (ID: {$fileId})",
        [
            'file_id' => $fileId,
            'file_name' => $fileRow['file_name'],
            'removed_recipient' => [
                'user_id' => (int)$recipient['user_id'],
                'email' => $recipient['user_email'],
                'username' => $recipient['username'] ?? null,
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'warning'
    );

    out(200, [
        'success' => true,
        'message' => 'Recipient removed successfully. They can no longer access this file.'
    ]);
}

function revokeShare(PDO $pdo, int $userId, array $data): void
{
    $fileId = (string)($data['file_id'] ?? '');
    if ($fileId === '') throw new Exception('File ID is required');

    // Get file info
    $infoStmt = $pdo->prepare("
        SELECT file_name, status
        FROM shared_files
        WHERE file_id = :file_id AND sender_id = :sender_id
        LIMIT 1
    ");
    $infoStmt->execute([':file_id' => $fileId, ':sender_id' => $userId]);
    $fileInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$fileInfo) {
        logUnauthorizedFileAction($pdo, $userId, 'UNAUTHORIZED_REVOKE_SHARE', 'Revoke share denied: not owner or file not found', [
            'file_id' => $fileId
        ]);
        throw new Exception('File not found or you do not have permission');
    }

    // Get all affected receiver IDs before revoking
    $receiversStmt = $pdo->prepare('
        SELECT receiver_id FROM shared_files WHERE file_id = :file_id
    ');
    $receiversStmt->execute([':file_id' => $fileId]);
    $receiverIds = $receiversStmt->fetchAll(PDO::FETCH_COLUMN);

    // Revoke access (set status to deleted)
    $updateStmt = $pdo->prepare("UPDATE shared_files SET status = 'deleted' WHERE file_id = :file_id AND sender_id = :sender_id");
    $updateStmt->execute([':file_id' => $fileId, ':sender_id' => $userId]);
    $affectedRows = $updateStmt->rowCount();

    // Notify about revocation
    notifyFileRevoked(
        $pdo,
        $userId,
        array_map('intval', $receiverIds),
        $fileId,
        $fileInfo['file_name']
    );

    // Audit log
    logFileAudit(
        $pdo,
        $userId,
        'FILE_SHARE_REVOKED',
        "User revoked sharing for file '{$fileInfo['file_name']}' (ID: {$fileId})",
        [
            'file_id' => $fileId,
            'file_name' => $fileInfo['file_name'],
            'previous_status' => $fileInfo['status'],
            'new_status' => 'deleted',
            'recipients_affected' => $affectedRows,
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'warning'
    );

    out(200, [
        'success' => true,
        'message' => "Access revoked for all {$affectedRows} recipient(s)."
    ]);
}

function deleteFile(PDO $pdo, int $userId, array $data): void
{
    $fileId = (string)($data['file_id'] ?? '');
    if ($fileId === '') throw new Exception('File ID is required');

    // Get file info
    $checkStmt = $pdo->prepare("
        SELECT file_name, storage_path, file_size
        FROM shared_files
        WHERE file_id = :file_id AND sender_id = :sender_id
        LIMIT 1
    ");
    $checkStmt->execute([':file_id' => $fileId, ':sender_id' => $userId]);
    $file = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        logUnauthorizedFileAction($pdo, $userId, 'UNAUTHORIZED_FILE_DELETE', 'File delete denied: not owner or file not found', [
            'file_id' => $fileId
        ]);
        throw new Exception('File not found or you do not have permission');
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM shared_files WHERE file_id = :file_id AND sender_id = :sender_id");
    $countStmt->execute([':file_id' => $fileId, ':sender_id' => $userId]);
    $rowsToDelete = (int)$countStmt->fetchColumn();

    // Get all affected receiver IDs before deletion
    $receiversStmt = $pdo->prepare('
        SELECT receiver_id FROM shared_files WHERE file_id = :file_id
    ');
    $receiversStmt->execute([':file_id' => $fileId]);
    $receiverIds = $receiversStmt->fetchAll(PDO::FETCH_COLUMN);

    // Notify about deletion (before actual deletion)
    notifyFileDeleted(
        $pdo,
        $userId,
        array_map('intval', $receiverIds),
        $fileId,
        $file['file_name']
    );

    // ✅ Do NOT log server path; log a hash for correlation if needed
    $pathHash = !empty($file['storage_path']) ? hash('sha256', (string)$file['storage_path']) : null;

    // Audit log
    logFileAudit(
        $pdo,
        $userId,
        'FILE_DELETED',
        "User permanently deleted file '{$file['file_name']}' (ID: {$fileId})",
        [
            'file_id' => $fileId,
            'file_name' => $file['file_name'],
            'file_size' => isset($file['file_size']) ? (int)$file['file_size'] : null,
            'storage_path_sha256' => $pathHash,
            'share_rows_deleted' => $rowsToDelete,
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'warning'
    );

    // Delete from database
    $deleteStmt = $pdo->prepare("DELETE FROM shared_files WHERE file_id = :file_id AND sender_id = :sender_id");
    $deleteStmt->execute([':file_id' => $fileId, ':sender_id' => $userId]);
    $deletedRecords = $deleteStmt->rowCount();

    // Delete physical file
    $storagePath = (string)($file['storage_path'] ?? '');
    if ($storagePath !== '' && file_exists($storagePath)) {
        @unlink($storagePath);
    }

    out(200, [
        'success' => true,
        'message' => "File permanently deleted. Removed {$deletedRecords} share record(s)."
    ]);
}