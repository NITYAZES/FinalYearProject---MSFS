<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

session_start();

require_once __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/* -------------------- audit helpers -------------------- */
function client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? $ip : '';
}

function user_agent(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return is_string($ua) ? substr($ua, 0, 512) : '';
}

/**
 * Matches your schema:
 * security_audit_log(user_id,event_type,event_category,severity,description,user_agent,metadata_json,created_at)
 */
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
        $metadata = array_merge(['ip' => client_ip()], $metadata);

        $metaJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($metaJson === false) $metaJson = '{}';

        $stmt = $pdo->prepare(
            'INSERT INTO security_audit_log
                (user_id, event_type, event_category, severity, description, user_agent, metadata_json, created_at)
             VALUES
                (:uid, :etype, :cat, :sev, :descr, :ua, :meta, NOW())'
        );

        $stmt->execute([
            ':uid'   => $userId,
            ':etype' => $eventType,
            ':cat'   => $category,
            ':sev'   => $severity,
            ':descr' => $description,
            ':ua'    => user_agent(),
            ':meta'  => $metaJson,
        ]);
    } catch (Throwable $e) {
        // audits must never break core features
        error_log('security_audit_log insert failed: ' . $e->getMessage());
    }
}

/* -------------------- auth -------------------- */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = (string)($_GET['action'] ?? '');

try {
    $pdo = db();

    switch ($action) {
        case 'get_files':
            getFiles($pdo, $userId);
            break;

        case 'get_stats':
            getStats($pdo, $userId);
            break;

        case 'extend_expiry':
            extendExpiry($pdo, $userId);
            break;

        case 'delete_file':
            deleteFileAction($pdo, $userId);
            break;

        case 'get_file_details':
            getFileDetails($pdo, $userId);
            break;

        default:
            // Optional audit: bad action
            audit_log($pdo, $userId, 'BAD_REQUEST', 'API', 'LOW', 'Invalid action requested', [
                'action' => $action
            ]);

            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ], JSON_UNESCAPED_UNICODE);
    error_log('files_api error: ' . $e->getMessage());
    exit;
}

/* -------------------- handlers -------------------- */

function getFiles(PDO $pdo, int $userId): void
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                file_id,
                file_name,
                file_size,
                mime_type,
                uploaded_at as uploadDate,
                expiry_time as expiryDate,
                decrypt_count as downloads,
                max_decrypt_count as maxDownloads,
                status,
                sender_id,
                receiver_id,
                CASE 
                    WHEN sender_id = ? THEN 'sent'
                    ELSE 'received'
                END as fileType
            FROM shared_files
            WHERE sender_id = ? OR receiver_id = ?
            ORDER BY uploaded_at DESC
        ");

        $stmt->execute([$userId, $userId, $userId]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($files as &$file) {
            $file['size'] = round(((int)$file['file_size']) / (1024 * 1024), 2);

            $expiry = (string)($file['expiryDate'] ?? '');
            $expiredByTime = ($expiry !== '' && strtotime($expiry) < time());
            $limitReached = ((int)$file['downloads'] >= (int)$file['maxDownloads']);

            if ($expiredByTime || $limitReached || ($file['status'] ?? '') === 'expired') {
                $file['status'] = 'expired';
            } else {
                $file['status'] = 'active';
            }
        }

        echo json_encode(['success' => true, 'files' => $files], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error fetching files'], JSON_UNESCAPED_UNICODE);
        error_log("Error fetching files: " . $e->getMessage());
    }
}

function getStats(PDO $pdo, int $userId): void
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM shared_files WHERE sender_id = ? OR receiver_id = ?");
        $stmt->execute([$userId, $userId]);
        $totalFiles = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active
            FROM shared_files
            WHERE (sender_id = ? OR receiver_id = ?)
              AND status = 'active'
              AND expiry_time > NOW()
              AND decrypt_count < max_decrypt_count
        ");
        $stmt->execute([$userId, $userId]);
        $activeFiles = (int)($stmt->fetch(PDO::FETCH_ASSOC)['active'] ?? 0);

        $stmt = $pdo->prepare("SELECT SUM(file_size) as total_size FROM shared_files WHERE sender_id = ?");
        $stmt->execute([$userId]);
        $totalSize = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total_size'] ?? 0);
        $totalStorageMB = round($totalSize / (1024 * 1024), 2);

        $stmt = $pdo->prepare("SELECT SUM(decrypt_count) as total_downloads FROM shared_files WHERE sender_id = ?");
        $stmt->execute([$userId]);
        $totalDownloads = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total_downloads'] ?? 0);

        echo json_encode([
            'success' => true,
            'stats' => [
                'totalFiles' => $totalFiles,
                'activeFiles' => $activeFiles,
                'totalStorage' => $totalStorageMB,
                'totalDownloads' => $totalDownloads
            ]
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error fetching stats'], JSON_UNESCAPED_UNICODE);
        error_log("Error fetching stats: " . $e->getMessage());
    }
}

function extendExpiry(PDO $pdo, int $userId): void
{
    $data = json_decode(file_get_contents('php://input') ?: '', true);

    if (!is_array($data) || empty($data['file_id']) || empty($data['new_expiry'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $fileId = (string)$data['file_id'];
    $newExpiry = (string)$data['new_expiry'];

    // Basic validation: must be valid datetime and in the future
    $ts = strtotime($newExpiry);
    if ($ts === false || $ts <= time()) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid new_expiry (must be future datetime)'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        // ownership + old expiry for audit
        $stmt = $pdo->prepare("SELECT sender_id, file_name, expiry_time FROM shared_files WHERE file_id = ? LIMIT 1");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file || (int)$file['sender_id'] !== $userId) {
            audit_log($pdo, $userId, 'UNAUTHORIZED_FILE_ACTION', 'FILE', 'WARNING', 'User tried to extend expiry without permission', [
                'file_id' => $fileId,
                'attempted_new_expiry' => $newExpiry
            ]);

            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to modify this file'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $oldExpiry = (string)($file['expiry_time'] ?? '');
        $fileName  = (string)($file['file_name'] ?? '');

        $stmt = $pdo->prepare("
            UPDATE shared_files
            SET expiry_time = ?,
                status = 'active'
            WHERE file_id = ?
        ");
        $stmt->execute([$newExpiry, $fileId]);

        audit_log($pdo, $userId, 'FILE_EXPIRY_EXTENDED', 'FILE', 'INFO', 'File expiry extended by sender', [
            'file_id' => $fileId,
            'file_name' => $fileName,
            'old_expiry' => $oldExpiry,
            'new_expiry' => $newExpiry
        ]);

        echo json_encode(['success' => true, 'message' => 'File expiry extended successfully'], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error extending expiry'], JSON_UNESCAPED_UNICODE);
        error_log("Error extending expiry: " . $e->getMessage());
    }
}

function deleteFileAction(PDO $pdo, int $userId): void
{
    $data = json_decode(file_get_contents('php://input') ?: '', true);

    if (!is_array($data) || empty($data['file_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File ID is required'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $fileId = (string)$data['file_id'];

    try {
        $stmt = $pdo->prepare("SELECT sender_id, storage_path, file_name, file_size FROM shared_files WHERE file_id = ? LIMIT 1");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file || (int)$file['sender_id'] !== $userId) {
            audit_log($pdo, $userId, 'UNAUTHORIZED_FILE_ACTION', 'FILE', 'WARNING', 'User tried to delete file without permission', [
                'file_id' => $fileId
            ]);

            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this file'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $fileName = (string)($file['file_name'] ?? '');
        $storagePath = (string)($file['storage_path'] ?? '');
        $fileSize = isset($file['file_size']) ? (int)$file['file_size'] : null;

        // Audit BEFORE deletion (so metadata is still available)
        audit_log($pdo, $userId, 'FILE_DELETED', 'FILE', 'WARNING', 'File deleted by sender', [
            'file_id' => $fileId,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'storage_path' => $storagePath
        ]);

        if ($storagePath !== '' && file_exists($storagePath)) {
            @unlink($storagePath);
        }

        $stmt = $pdo->prepare("DELETE FROM shared_files WHERE file_id = ?");
        $stmt->execute([$fileId]);

        $stmt = $pdo->prepare("DELETE FROM encryption_metrics WHERE file_id = ?");
        $stmt->execute([$fileId]);

        echo json_encode(['success' => true, 'message' => 'File deleted successfully'], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error deleting file'], JSON_UNESCAPED_UNICODE);
        error_log("Error deleting file: " . $e->getMessage());
    }
}

function getFileDetails(PDO $pdo, int $userId): void
{
    $fileId = (string)($_GET['file_id'] ?? '');

    if ($fileId === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File ID is required'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT 
                sf.*,
                u1.username as sender_username,
                u1.user_fullname as sender_name,
                u2.username as receiver_username,
                u2.user_fullname as receiver_name,
                em.encryption_score,
                em.encryption_rating,
                em.encryption_percentage,
                em.encryption_time_ms,
                em.size_overhead_percent
            FROM shared_files sf
            LEFT JOIN users u1 ON sf.sender_id = u1.user_id
            LEFT JOIN users u2 ON sf.receiver_id = u2.user_id
            LEFT JOIN encryption_metrics em ON sf.file_id = em.file_id
            WHERE sf.file_id = ?
              AND (sf.sender_id = ? OR sf.receiver_id = ?)
            LIMIT 1
        ");

        $stmt->execute([$fileId, $userId, $userId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File not found'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Optional audit: details viewed
        audit_log($pdo, $userId, 'FILE_DETAILS_VIEWED', 'FILE', 'INFO', 'User viewed file details', [
            'file_id' => $fileId
        ]);

        echo json_encode(['success' => true, 'file' => $file], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error fetching file details'], JSON_UNESCAPED_UNICODE);
        error_log("Error fetching file details: " . $e->getMessage());
    }
}
