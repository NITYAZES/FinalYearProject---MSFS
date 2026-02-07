<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Prevent browser caching (blocks back/forward after logout)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Handle preflight requests
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';

// Enable error logging
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ✅ Admin auth guard (critical)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'admin')) {
    json_out(401, ['success' => false, 'message' => 'Unauthorized. Admin access required.']);
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                getAllFiles();
            } elseif ($action === 'details' && isset($_GET['file_id'])) {
                getFileDetails((string)$_GET['file_id']);
            } elseif ($action === 'stats') {
                getFileStatistics();
            } elseif ($action === 'encryption' && isset($_GET['file_id'])) {
                getEncryptionMetrics((string)$_GET['file_id']);
            } elseif ($action === 'distribution') {
                getEncryptionDistribution();
            } elseif ($action === 'download' && isset($_GET['file_id'])) {
                downloadEncryptedFile((string)$_GET['file_id']);
            } else {
                json_out(400, ['success' => false, 'message' => 'Invalid action']);
            }
            break;

        case 'DELETE':
            if ($action === 'delete' && isset($_GET['file_id'])) {
                deleteFile((string)$_GET['file_id']);
            } else {
                json_out(400, ['success' => false, 'message' => 'Invalid action']);
            }
            break;

        default:
            json_out(405, ['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log('Admin Manage Files Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    json_out(500, ['success' => false, 'message' => 'Server error']);
}

/**
 * Get all files with their details
 */
function getAllFiles(): void {
    $pdo = db();

    try {
        $sql = "
            SELECT 
                sf.file_id,
                sf.file_name,
                sf.file_size,
                sf.mime_type,
                sf.status,
                sf.uploaded_at,
                sf.expiry_time,
                sf.max_decrypt_count,
                sf.decrypt_count,
                sf.last_accessed_at,
                sender.user_fullname as sender_name,
                sender.user_email as sender_email,
                receiver.user_fullname as receiver_name,
                receiver.user_email as receiver_email,
                COALESCE(em.encryption_score, 0) as encryption_score,
                COALESCE(em.encryption_rating, 'Unknown') as encryption_rating,
                COALESCE(em.encryption_percentage, 0) as encryption_percentage
            FROM shared_files sf
            LEFT JOIN users sender ON sf.sender_id = sender.user_id
            LEFT JOIN users receiver ON sf.receiver_id = receiver.user_id
            LEFT JOIN encryption_metrics em ON sf.file_id = em.file_id
            ORDER BY sf.uploaded_at DESC
        ";

        $stmt = $pdo->query($sql);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($files as &$file) {
            $file['file_size'] = (int)$file['file_size'];
            $file['max_decrypt_count'] = (int)$file['max_decrypt_count'];
            $file['decrypt_count'] = (int)$file['decrypt_count'];
            $file['encryption_score'] = (int)$file['encryption_score'];
            $file['encryption_percentage'] = (float)$file['encryption_percentage'];

            $file['is_expired'] = !empty($file['expiry_time']) ? (strtotime((string)$file['expiry_time']) < time()) : false;
            $file['file_type_category'] = getFileTypeCategory($file['mime_type'] ?? null);
        }

        json_out(200, ['success' => true, 'files' => $files]);
    } catch (PDOException $e) {
        error_log('getAllFiles error: ' . $e->getMessage());
        json_out(500, ['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get detailed information about a specific file
 */
function getFileDetails(string $fileId): void {
    $pdo = db();

    try {
        $sql = "
            SELECT 
                sf.*,
                sender.user_fullname as sender_name,
                sender.user_email as sender_email,
                sender.username as sender_username,
                receiver.user_fullname as receiver_name,
                receiver.user_email as receiver_email,
                receiver.username as receiver_username
            FROM shared_files sf
            LEFT JOIN users sender ON sf.sender_id = sender.user_id
            LEFT JOIN users receiver ON sf.receiver_id = receiver.user_id
            WHERE sf.file_id = ?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            json_out(404, ['success' => false, 'message' => 'File not found']);
            return;
        }

        // 🔥 LOG: admin viewed file details
        logAdminAction(
            $pdo,
            'admin_viewed_file_details',
            "Admin viewed details for file '{$file['file_name']}' (ID: {$fileId})",
            [
                'file_id' => $fileId,
                'file_name' => $file['file_name'],
                'file_size' => (int)($file['file_size'] ?? 0),
                'sender_id' => $file['sender_id'] ?? null,
                'receiver_id' => $file['receiver_id'] ?? null,
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'info'
        );

        $file['policy'] = json_decode((string)($file['policy_json'] ?? '{}'), true);

        $sql = "
            SELECT 
                fal.action,
                fal.ip_address,
                fal.user_agent,
                fal.accessed_at,
                u.user_fullname,
                u.user_email
            FROM file_access_log fal
            LEFT JOIN users u ON fal.user_id = u.user_id
            WHERE fal.file_id = ?
            ORDER BY fal.accessed_at DESC
            LIMIT 20
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fileId]);
        $file['access_log'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT * FROM encryption_metrics WHERE file_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fileId]);
        $file['encryption_metrics'] = $stmt->fetch(PDO::FETCH_ASSOC);

        $sql = "
            SELECT 
                fr.*,
                u.user_fullname,
                u.user_email
            FROM file_recipients fr
            LEFT JOIN users u ON fr.user_id = u.user_id
            WHERE fr.file_id = ?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fileId]);
        $file['recipients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $file['file_size'] = (int)($file['file_size'] ?? 0);
        $file['max_decrypt_count'] = (int)($file['max_decrypt_count'] ?? 0);
        $file['decrypt_count'] = (int)($file['decrypt_count'] ?? 0);
        $file['is_expired'] = !empty($file['expiry_time']) ? (strtotime((string)$file['expiry_time']) < time()) : false;
        $file['file_type_category'] = getFileTypeCategory($file['mime_type'] ?? null);

        json_out(200, ['success' => true, 'file' => $file]);

    } catch (Exception $e) {
        error_log('getFileDetails error: ' . $e->getMessage());
        json_out(500, ['success' => false, 'message' => 'Error fetching file details']);
    }
}

function getFileStatistics(): void {
    $pdo = db();

    try {
        $sql = "
            SELECT 
                COUNT(*) as total_files,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_files,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_files,
                SUM(CASE WHEN status = 'deleted' THEN 1 ELSE 0 END) as deleted_files,
                COALESCE(SUM(file_size), 0) as total_storage,
                COALESCE(AVG(file_size), 0) as avg_file_size
            FROM shared_files
        ";

        $stmt = $pdo->query($sql);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $sql = "SELECT COALESCE(AVG(encryption_score), 0) as avg_encryption FROM encryption_metrics";
        $stmt = $pdo->query($sql);
        $encStats = $stmt->fetch(PDO::FETCH_ASSOC);

        $sql = "
            SELECT 
                SUBSTRING_INDEX(mime_type, '/', 1) as type_category,
                COUNT(*) as count
            FROM shared_files
            WHERE mime_type IS NOT NULL
            GROUP BY type_category
        ";
        $stmt = $pdo->query($sql);
        $typeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($stats as $key => $value) {
            $stats[$key] = ($key === 'avg_file_size') ? (float)$value : (int)$value;
        }

        $stats['avg_encryption'] = round((float)($encStats['avg_encryption'] ?? 0), 2);
        $stats['files_by_type'] = $typeStats;

        json_out(200, ['success' => true, 'statistics' => $stats]);
    } catch (PDOException $e) {
        error_log('getFileStatistics error: ' . $e->getMessage());
        json_out(500, ['success' => false, 'message' => 'Database error']);
    }
}

function getEncryptionMetrics(string $fileId): void {
    $pdo = db();

    try {
        $sql = "SELECT * FROM encryption_metrics WHERE file_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fileId]);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$metrics) {
            json_out(404, ['success' => false, 'message' => 'Encryption metrics not found']);
            return;
        }

        $metrics['score_breakdown'] = json_decode((string)($metrics['score_breakdown_json'] ?? '{}'), true);
        $metrics['recommendations'] = json_decode((string)($metrics['recommendations_json'] ?? '[]'), true);

        $metrics['encryption_score'] = (int)($metrics['encryption_score'] ?? 0);
        $metrics['encryption_percentage'] = (float)($metrics['encryption_percentage'] ?? 0);
        $metrics['rsa_key_size'] = (int)($metrics['rsa_key_size'] ?? 0);
        $metrics['aes_key_size'] = (int)($metrics['aes_key_size'] ?? 0);
        $metrics['original_size'] = (int)($metrics['original_size'] ?? 0);
        $metrics['encrypted_size'] = (int)($metrics['encrypted_size'] ?? 0);

        json_out(200, ['success' => true, 'metrics' => $metrics]);

    } catch (Exception $e) {
        error_log('getEncryptionMetrics error: ' . $e->getMessage());
        json_out(500, ['success' => false, 'message' => 'Error fetching metrics']);
    }
}

function getEncryptionDistribution(): void {
    $pdo = db();

    try {
        $sql = "
            SELECT 
                CASE 
                    WHEN encryption_score >= 95 THEN 'excellent'
                    WHEN encryption_score >= 80 THEN 'good'
                    WHEN encryption_score >= 60 THEN 'fair'
                    ELSE 'poor'
                END as rating_category,
                COUNT(*) as count,
                AVG(encryption_score) as avg_score
            FROM encryption_metrics
            GROUP BY rating_category
            ORDER BY avg_score DESC
        ";

        $stmt = $pdo->query($sql);
        $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = array_sum(array_column($distribution, 'count'));

        foreach ($distribution as &$item) {
            $item['count'] = (int)$item['count'];
            $item['avg_score'] = round((float)$item['avg_score'], 2);
            $item['percentage'] = $total > 0 ? round(($item['count'] / $total) * 100, 2) : 0;

            switch ($item['rating_category']) {
                case 'excellent': $item['score_range'] = '95-100'; break;
                case 'good':      $item['score_range'] = '80-94';  break;
                case 'fair':      $item['score_range'] = '60-79';  break;
                default:          $item['score_range'] = 'Below 60'; break;
            }
        }

        json_out(200, ['success' => true, 'distribution' => $distribution]);

    } catch (PDOException $e) {
        error_log('getEncryptionDistribution error: ' . $e->getMessage());
        json_out(500, ['success' => false, 'message' => 'Database error']);
    }
}

function downloadEncryptedFile(string $fileId): void {
    $pdo = db();

    try {
        // ✅ updated query so logging has full metadata
        $sql = "SELECT file_name, storage_path, file_size, sender_id, receiver_id FROM shared_files WHERE file_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            json_out(404, ['success' => false, 'message' => 'File not found']);
            return;
        }

        if (!file_exists((string)$file['storage_path'])) {
            json_out(404, ['success' => false, 'message' => 'File not found on server']);
            return;
        }

        // 🔥 LOG: admin downloaded encrypted file
        logAdminAction(
            $pdo,
            'admin_downloaded_encrypted_file',
            "Admin downloaded encrypted file '{$file['file_name']}' (ID: {$fileId})",
            [
                'file_id' => $fileId,
                'file_name' => $file['file_name'],
                'file_size' => (int)($file['file_size'] ?? 0),
                'storage_path' => $file['storage_path'],
                'sender_id' => $file['sender_id'] ?? null,
                'receiver_id' => $file['receiver_id'] ?? null,
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'info'
        );

        // IMPORTANT: override JSON headers for file download
        header_remove('Content-Type');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename((string)$file['file_name']) . '.enc"');
        header('Content-Length: ' . filesize((string)$file['storage_path']));

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile((string)$file['storage_path']);
        exit;

    } catch (Exception $e) {
        error_log('downloadEncryptedFile error: ' . $e->getMessage());
        json_out(500, ['success' => false, 'message' => 'Error downloading file']);
    }
}

function deleteFile(string $fileId): void {
    $pdo = db();

    try {
        // ✅ updated query so logging has full metadata
        $sql = "SELECT file_name, storage_path, file_size, sender_id, receiver_id FROM shared_files WHERE file_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            json_out(404, ['success' => false, 'message' => 'File not found']);
            return;
        }

        $pdo->beginTransaction();

        // 🔥 LOG BEFORE deletion
        logAdminAction(
            $pdo,
            'admin_file_deleted',
            "Admin permanently deleted file '{$file['file_name']}' (ID: {$fileId})",
            [
                'file_id' => $fileId,
                'file_name' => $file['file_name'],
                'file_size' => (int)($file['file_size'] ?? 0),
                'storage_path' => $file['storage_path'],
                'sender_id' => $file['sender_id'] ?? null,
                'receiver_id' => $file['receiver_id'] ?? null,
                'admin_id' => (int)($_SESSION['user_id'] ?? 0),
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'warning'
        );

        if (file_exists((string)$file['storage_path'])) {
            unlink((string)$file['storage_path']);
        }

        $stmt = $pdo->prepare("DELETE FROM shared_files WHERE file_id = ?");
        $stmt->execute([$fileId]);

        $pdo->commit();

        json_out(200, ['success' => true, 'message' => 'File deleted successfully']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('deleteFile error: ' . $e->getMessage());
        json_out(500, ['success' => false, 'message' => 'Failed to delete file']);
    }
}


function logAdminAction(
    PDO $pdo,
    string $eventType,
    string $description,
    array $metadata = [],
    string $severity = 'info'
): void {
    try {
        $sql = "
            INSERT INTO security_audit_log
            (
                user_id,
                event_type,
                event_category,
                severity,
                description,
                user_agent,
                metadata_json,
                created_at
            )
            VALUES
            (:user_id, :event_type, 'admin', :severity, :description, :ua, :metadata, NOW())
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => (int)($_SESSION['user_id'] ?? 0),
            ':event_type' => $eventType,
            ':severity' => $severity,
            ':description' => $description,
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE)
        ]);
    } catch (PDOException $e) {
        error_log('❌ Failed to log admin action: ' . $e->getMessage());
    }
}

function getFileTypeCategory(?string $mimeType): string {
    if (!$mimeType) return 'unknown';
    $category = explode('/', $mimeType)[0];
    $categories = ['image', 'audio', 'video', 'application', 'text'];
    return in_array($category, $categories, true) ? $category : 'other';
}
