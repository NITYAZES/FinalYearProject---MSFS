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

        case 'reactivate_share':
            if ($method !== 'POST') throw new Exception('POST method required');
            $data = json_decode(file_get_contents('php://input') ?: '[]', true);
            reactivateShare($pdo, $user_id, is_array($data) ? $data : []);
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
        $groupedFiles[$fileId]['total_views'] += (int)($file['decrypt_count'] ?? 0);
    }

    $statsQuery = "
        SELECT 
            COUNT(DISTINCT file_id) as total_shared,
            COUNT(*) as total_recipients,
            SUM(decrypt_count) as total_views,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_shares
        FROM shared_files
        WHERE sender_id = :user_id
    ";

    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute([':user_id' => $userId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    out(200, [
        'success' => true,
        'files' => array_values($groupedFiles),
        'stats' => [
            'total_shared' => (int)($stats['total_shared'] ?? 0),
            'total_recipients' => (int)($stats['total_recipients'] ?? 0),
            'total_views' => (int)($stats['total_views'] ?? 0),
            'active_shares' => (int)($stats['active_shares'] ?? 0)
        ]
    ]);
}

function editPolicy(PDO $pdo, int $userId, array $data): void
{
    $fileId = (string)($data['file_id'] ?? '');
    $expiryTime = (string)($data['expiry_time'] ?? '');
    $maxDecryptCount = (int)($data['max_decrypt_count'] ?? 0);

    if ($fileId === '' || $expiryTime === '' || $maxDecryptCount < 1) {
        throw new Exception('File ID, expiry time, and max decrypt count (≥1) are required');
    }

    // Verify ownership
    $checkStmt = $pdo->prepare("
        SELECT file_name 
        FROM shared_files 
        WHERE file_id = :file_id AND sender_id = :sender_id 
        LIMIT 1
    ");
    $checkStmt->execute([':file_id' => $fileId, ':sender_id' => $userId]);

    if (!$checkStmt->fetch()) {
        logUnauthorizedFileAction($pdo, $userId, 'UNAUTHORIZED_POLICY_EDIT', 'Policy edit denied: not owner or file not found', [
            'file_id' => $fileId
        ]);
        throw new Exception('File not found or you do not have permission');
    }

    // Update policy
    $updateStmt = $pdo->prepare("
        UPDATE shared_files
        SET expiry_time = :expiry_time, max_decrypt_count = :max_decrypt_count
        WHERE file_id = :file_id AND sender_id = :sender_id
    ");

    $updateStmt->execute([
        ':file_id' => $fileId,
        ':sender_id' => $userId,
        ':expiry_time' => $expiryTime,
        ':max_decrypt_count' => $maxDecryptCount
    ]);

    // Audit log
    logFileAudit(
        $pdo,
        $userId,
        'FILE_POLICY_UPDATED',
        "User updated policy for file ID: {$fileId}",
        [
            'file_id' => $fileId,
            'expiry_time' => $expiryTime,
            'max_decrypt_count' => $maxDecryptCount,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    );

    out(200, [
        'success' => true,
        'message' => 'Policy updated successfully'
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

    // Check if already revoked
    if ($fileInfo['status'] === 'revoked') {
        throw new Exception('File is already revoked');
    }

    // Get all affected receiver IDs before revoking
    $receiversStmt = $pdo->prepare('
        SELECT receiver_id FROM shared_files WHERE file_id = :file_id
    ');
    $receiversStmt->execute([':file_id' => $fileId]);
    $receiverIds = $receiversStmt->fetchAll(PDO::FETCH_COLUMN);

    // Revoke access (set status to 'revoked' - SOFT DELETE)
    $updateStmt = $pdo->prepare("
        UPDATE shared_files 
        SET status = 'revoked' 
        WHERE file_id = :file_id AND sender_id = :sender_id
    ");
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
            'new_status' => 'revoked',
            'recipients_affected' => $affectedRows,
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'warning'
    );

    out(200, [
        'success' => true,
        'message' => "Access revoked for all {$affectedRows} recipient(s). File can be reactivated later if needed."
    ]);
}

function reactivateShare(PDO $pdo, int $userId, array $data): void
{
    $fileId = (string)($data['file_id'] ?? '');
    if ($fileId === '') throw new Exception('File ID is required');

    // Get file info and verify it's revoked
    $infoStmt = $pdo->prepare("
        SELECT file_name, status, expiry_time, storage_path
        FROM shared_files
        WHERE file_id = :file_id AND sender_id = :sender_id
        LIMIT 1
    ");
    $infoStmt->execute([':file_id' => $fileId, ':sender_id' => $userId]);
    $fileInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$fileInfo) {
        logUnauthorizedFileAction($pdo, $userId, 'UNAUTHORIZED_REACTIVATE_SHARE', 'Reactivate share denied: not owner or file not found', [
            'file_id' => $fileId
        ]);
        throw new Exception('File not found or you do not have permission');
    }

    // Check if file is revoked
    if ($fileInfo['status'] !== 'revoked') {
        throw new Exception('Only revoked files can be reactivated. Current status: ' . $fileInfo['status']);
    }

    // Check if file has expired
    if ($fileInfo['expiry_time']) {
        $expiryDate = new DateTime($fileInfo['expiry_time']);
        $now = new DateTime();
        if ($expiryDate < $now) {
            throw new Exception('Cannot reactivate an expired file. Please update the expiry time first.');
        }
    }

    // Verify physical file still exists
    $storagePath = (string)($fileInfo['storage_path'] ?? '');
    if ($storagePath !== '' && !file_exists($storagePath)) {
        logFileAudit(
            $pdo,
            $userId,
            'FILE_REACTIVATE_FAILED_MISSING',
            "Reactivation failed: physical file missing for '{$fileInfo['file_name']}' (ID: {$fileId})",
            [
                'file_id' => $fileId,
                'file_name' => $fileInfo['file_name']
            ],
            'error'
        );
        throw new Exception('Cannot reactivate: physical file no longer exists on server');
    }

    // Get all affected receiver IDs
    $receiversStmt = $pdo->prepare('
        SELECT receiver_id FROM shared_files WHERE file_id = :file_id
    ');
    $receiversStmt->execute([':file_id' => $fileId]);
    $receiverIds = $receiversStmt->fetchAll(PDO::FETCH_COLUMN);

    // Reactivate (set status back to 'active')
    $updateStmt = $pdo->prepare("
        UPDATE shared_files 
        SET status = 'active' 
        WHERE file_id = :file_id AND sender_id = :sender_id
    ");
    $updateStmt->execute([':file_id' => $fileId, ':sender_id' => $userId]);
    $affectedRows = $updateStmt->rowCount();

    // Notify recipients about reactivation
    notifyFileReactivated(
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
        'FILE_SHARE_REACTIVATED',
        "User reactivated sharing for file '{$fileInfo['file_name']}' (ID: {$fileId})",
        [
            'file_id' => $fileId,
            'file_name' => $fileInfo['file_name'],
            'previous_status' => 'revoked',
            'new_status' => 'active',
            'recipients_affected' => $affectedRows,
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'info'
    );

    out(200, [
        'success' => true,
        'message' => "File reactivated successfully! All {$affectedRows} recipient(s) can now access it again."
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