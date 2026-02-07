<?php
declare(strict_types=1);

// Enable error reporting but do not display to users
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent browser caching (blocks back/forward after logout)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Always JSON
header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in
if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized - No user session'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

/**
 * Helpers
 */
function ensurePost(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function readJsonBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}


function formatTimeLeft(?string $expiryTime): string {
    if (!$expiryTime) return 'unknown time';

    $expiryTs = strtotime($expiryTime);
    if ($expiryTs === false) return 'unknown time';

    $secondsLeft = $expiryTs - time();
    if ($secondsLeft <= 0) return '0 minutes';

    $days    = intdiv($secondsLeft, 86400);
    $hours   = intdiv($secondsLeft % 86400, 3600);
    $minutes = intdiv($secondsLeft % 3600, 60);

    $parts = [];

    if ($days > 0) {
        $parts[] = $days . ' day' . ($days === 1 ? '' : 's');
    }
    if ($hours > 0) {
        $parts[] = $hours . ' hour' . ($hours === 1 ? '' : 's');
    }
    if ($days === 0 && $minutes > 0) {
        $parts[] = $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }

    return $parts ? implode(' ', $parts) : '0 minutes';
}

try {
    // Database connection
    $pdo = new PDO(
        "mysql:host=localhost;dbname=multimediasecurefilesharing;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_notifications':
            getNotifications($pdo, $user_id);
            break;

        case 'mark_read':
            ensurePost();
            markAsRead($pdo, $user_id, readJsonBody());
            break;

        case 'mark_all_read':
            ensurePost();
            markAllAsRead($pdo, $user_id);
            break;

        case 'delete_notification':
            ensurePost();
            deleteNotification($pdo, $user_id, readJsonBody());
            break;

        case 'clear_all':
            ensurePost();
            clearAllNotifications($pdo, $user_id);
            break;

        case 'get_count':
            getUnreadCount($pdo, $user_id);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ], JSON_UNESCAPED_UNICODE);
            exit;
    }
} catch (PDOException $e) {
    error_log('notifications.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('notifications.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function getNotifications($pdo, $userId)
{
    try {
        $notifications = [];
        $userId = (int)$userId;

        ensureNotificationsTable($pdo);

        // Get dismissed notification IDs
        $dismissedQuery = "SELECT notification_id FROM user_notifications WHERE user_id = ? AND dismissed = 1";
        $stmt = $pdo->prepare($dismissedQuery);
        $stmt->execute([$userId]);
        $dismissed = $stmt->fetchAll(PDO::FETCH_COLUMN);

  
        $sharedWithYouQuery = "
            SELECT 
                sf.file_id,
                sf.file_name,
                sf.uploaded_at as timestamp,
                sf.sender_id,
                sf.status,
                sf.expiry_time
            FROM shared_files sf
            WHERE sf.receiver_id = ?
            ORDER BY sf.uploaded_at DESC
            LIMIT 20
        ";

        $stmt = $pdo->prepare($sharedWithYouQuery);
        $stmt->execute([$userId]);
        $sharedWithYou = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sharedWithYou as $file) {
            $notifId = 'shared_' . $file['file_id'];

            if (in_array($notifId, $dismissed, true)) {
                continue;
            }

            $userQuery = "SELECT username, user_fullname FROM users WHERE user_id = ?";
            $userStmt = $pdo->prepare($userQuery);
            $userStmt->execute([$file['sender_id']]);
            $sender = $userStmt->fetch(PDO::FETCH_ASSOC);

            $senderName = $sender ? ($sender['user_fullname'] ?: $sender['username']) : 'Unknown User';
            $isExpired = !empty($file['expiry_time']) && strtotime($file['expiry_time']) < time();

            $notifications[] = [
                'id' => $notifId,
                'type' => 'file_shared_with_you',
                'title' => 'New File Shared',
                'message' => $senderName . ' shared "' . $file['file_name'] . '" with you',
                'timestamp' => $file['timestamp'],
                'icon' => '📥',
                'is_read' => isNotificationRead($pdo, $userId, $notifId),
                'file_id' => $file['file_id'],
                'status' => $isExpired ? 'expired' : $file['status']
            ];
        }


        $yourSharesQuery = "
            SELECT 
                sf.file_id,
                sf.file_name,
                sf.uploaded_at as timestamp,
                sf.decrypt_count,
                sf.max_decrypt_count,
                sf.receiver_id
            FROM shared_files sf
            WHERE sf.sender_id = ?
            AND sf.decrypt_count > 0
            ORDER BY sf.uploaded_at DESC
            LIMIT 20
        ";

        $stmt = $pdo->prepare($yourSharesQuery);
        $stmt->execute([$userId]);
        $yourShares = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($yourShares as $file) {
            $notifId = 'accessed_' . $file['file_id'];

            if (in_array($notifId, $dismissed, true)) {
                continue;
            }

            $userQuery = "SELECT username, user_fullname FROM users WHERE user_id = ?";
            $userStmt = $pdo->prepare($userQuery);
            $userStmt->execute([$file['receiver_id']]);
            $receiver = $userStmt->fetch(PDO::FETCH_ASSOC);

            $receiverName = $receiver ? ($receiver['user_fullname'] ?: $receiver['username']) : 'Unknown User';

            $notifications[] = [
                'id' => $notifId,
                'type' => 'file_accessed',
                'title' => 'File Accessed',
                'message' => $receiverName . ' accessed "' . $file['file_name'] . '" (' . $file['decrypt_count'] . '/' . $file['max_decrypt_count'] . ' times)',
                'timestamp' => $file['timestamp'],
                'icon' => '👁️',
                'is_read' => isNotificationRead($pdo, $userId, $notifId),
                'file_id' => $file['file_id']
            ];
        }

  
        $expiringSoonQuery = "
            SELECT 
                sf.file_id,
                sf.file_name,
                sf.expiry_time
            FROM shared_files sf
            WHERE sf.receiver_id = ?
            AND sf.status = 'active'
            AND sf.expiry_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
            ORDER BY sf.expiry_time ASC
            LIMIT 10
        ";

        $stmt = $pdo->prepare($expiringSoonQuery);
        $stmt->execute([$userId]);
        $expiringSoon = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expiringSoon as $file) {
            $notifId = 'expiring_' . $file['file_id'];

            if (in_array($notifId, $dismissed, true)) {
                continue;
            }

           
            $timeLeftText = formatTimeLeft($file['expiry_time']);

            $notifications[] = [
                'id' => $notifId,
                'type' => 'file_expiring',
                'title' => 'File Expiring Soon',
                'message' => '"' . $file['file_name'] . '" expires in ' . $timeLeftText,
                'timestamp' => $file['expiry_time'],
                'icon' => '⏰',
                'is_read' => isNotificationRead($pdo, $userId, $notifId),
                'file_id' => $file['file_id'],
                'priority' => 'high'
            ];
        }

   
        usort($notifications, function ($a, $b) {
            return strtotime($b['timestamp']) <=> strtotime($a['timestamp']);
        });


        $unreadCount = count(array_filter($notifications, function ($n) {
            return empty($n['is_read']);
        }));

        echo json_encode([
            'success' => true,
            'notifications' => array_slice($notifications, 0, 20),
            'unread_count' => $unreadCount
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('getNotifications error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching notifications'
        ], JSON_UNESCAPED_UNICODE);
    }
}

function ensureNotificationsTable($pdo)
{
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS user_notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            notification_id VARCHAR(100) NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            dismissed TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_notification (user_id, notification_id),
            KEY idx_user (user_id),
            KEY idx_notification (notification_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $pdo->exec($createTableQuery);
}

function isNotificationRead($pdo, $userId, $notificationId)
{
    $query = "SELECT is_read FROM user_notifications WHERE user_id = ? AND notification_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([(int)$userId, (string)$notificationId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (bool)$result['is_read'] : false;
}

function markAsRead($pdo, $userId, $data)
{
    try {
        $notificationId = $data['notification_id'] ?? null;
        if (!$notificationId) {
            throw new Exception('Notification ID is required');
        }

        $query = "
            INSERT INTO user_notifications (user_id, notification_id, is_read)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE is_read = 1, updated_at = CURRENT_TIMESTAMP
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([(int)$userId, (string)$notificationId]);

        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read'
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('markAsRead error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error marking notification as read'
        ], JSON_UNESCAPED_UNICODE);
    }
}

function markAllAsRead($pdo, $userId)
{
    try {
      
        $query = "
            SELECT CONCAT('shared_', file_id) as notif_id FROM shared_files WHERE receiver_id = ?
            UNION
            SELECT CONCAT('accessed_', file_id) as notif_id FROM shared_files WHERE sender_id = ? AND decrypt_count > 0
            UNION
            SELECT CONCAT('expiring_', file_id) as notif_id FROM shared_files 
            WHERE receiver_id = ?
            AND status = 'active' 
            AND expiry_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([(int)$userId, (int)$userId, (int)$userId]);
        $notificationIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($notificationIds as $notifId) {
            $insertQuery = "
                INSERT INTO user_notifications (user_id, notification_id, is_read)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE is_read = 1, updated_at = CURRENT_TIMESTAMP
            ";
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->execute([(int)$userId, (string)$notifId]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read',
            'count' => count($notificationIds)
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('markAllAsRead error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error marking all notifications as read'
        ], JSON_UNESCAPED_UNICODE);
    }
}

function deleteNotification($pdo, $userId, $data)
{
    try {
        $notificationId = $data['notification_id'] ?? null;
        if (!$notificationId) {
            throw new Exception('Notification ID is required');
        }

        $query = "
            INSERT INTO user_notifications (user_id, notification_id, dismissed)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE dismissed = 1, updated_at = CURRENT_TIMESTAMP
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([(int)$userId, (string)$notificationId]);

        echo json_encode([
            'success' => true,
            'message' => 'Notification deleted successfully'
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('deleteNotification error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting notification'
        ], JSON_UNESCAPED_UNICODE);
    }
}

function clearAllNotifications($pdo, $userId)
{
    try {
       
        $query = "
            SELECT CONCAT('shared_', file_id) as notif_id FROM shared_files WHERE receiver_id = ?
            UNION
            SELECT CONCAT('accessed_', file_id) as notif_id FROM shared_files WHERE sender_id = ? AND decrypt_count > 0
            UNION
            SELECT CONCAT('expiring_', file_id) as notif_id FROM shared_files 
            WHERE receiver_id = ?
            AND status = 'active' 
            AND expiry_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([(int)$userId, (int)$userId, (int)$userId]);
        $notificationIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($notificationIds as $notifId) {
            $insertQuery = "
                INSERT INTO user_notifications (user_id, notification_id, dismissed)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE dismissed = 1, updated_at = CURRENT_TIMESTAMP
            ";
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->execute([(int)$userId, (string)$notifId]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'All notifications cleared',
            'count' => count($notificationIds)
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('clearAllNotifications error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error clearing notifications'
        ], JSON_UNESCAPED_UNICODE);
    }
}

function getUnreadCount($pdo, $userId)
{
    try {
        $userId = (int)$userId;

        ensureNotificationsTable($pdo);

        $dismissedQuery = "SELECT notification_id FROM user_notifications WHERE user_id = ? AND dismissed = 1";
        $stmt = $pdo->prepare($dismissedQuery);
        $stmt->execute([$userId]);
        $dismissed = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $readQuery = "SELECT notification_id FROM user_notifications WHERE user_id = ? AND is_read = 1";
        $stmt = $pdo->prepare($readQuery);
        $stmt->execute([$userId]);
        $read = $stmt->fetchAll(PDO::FETCH_COLUMN);

        
        $query = "
            SELECT COUNT(*) as count FROM (
                SELECT CONCAT('shared_', file_id) as notif_id FROM shared_files 
                WHERE receiver_id = ? AND uploaded_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                UNION
                SELECT CONCAT('accessed_', file_id) as notif_id FROM shared_files 
                WHERE sender_id = ? AND decrypt_count > 0 AND uploaded_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                UNION
                SELECT CONCAT('expiring_', file_id) as notif_id FROM shared_files 
                WHERE receiver_id = ?
                AND status = 'active' 
                AND expiry_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
            ) as all_notifications
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $userId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $unreadCount = max(0, (int)$result['count'] - count(array_unique(array_merge($dismissed, $read))));

        echo json_encode([
            'success' => true,
            'count' => $unreadCount
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('getUnreadCount error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching count'
        ], JSON_UNESCAPED_UNICODE);
    }
}