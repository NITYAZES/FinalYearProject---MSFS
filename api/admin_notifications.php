<?php
declare(strict_types=1);

// Production-safe errors (log only)
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Prevent browser caching (blocks back/forward after logout)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

function respond(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // ✅ Admin auth guard
    if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
        respond(401, ['ok' => false, 'error' => 'Not authenticated']);
    }
    if (($_SESSION['role'] ?? '') !== 'admin') {
        respond(403, ['ok' => false, 'error' => 'Admin access required']);
    }

    $adminId = (int)$_SESSION['user_id'];

    // Database connection (kept your approach)
    $DB_HOST = 'localhost';
    $DB_NAME = 'multimediasecurefilesharing';
    $DB_USER = 'root';
    $DB_PASS = '';

    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Ensure table exists (kept your logic)
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'admin_notifications'")->fetch();
    if (!$tableCheck) {
        $pdo->exec("
            CREATE TABLE `admin_notifications` (
              `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
              `admin_id` bigint(20) UNSIGNED NOT NULL,
              `notification_id` varchar(100) NOT NULL,
              `notification_type` varchar(50) NOT NULL,
              `title` varchar(255) NOT NULL,
              `message` text NOT NULL,
              `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
              `category` enum('security','user','file','system','audit') DEFAULT 'system',
              `is_read` tinyint(1) DEFAULT 0,
              `dismissed` tinyint(1) DEFAULT 0,
              `action_url` varchar(512) DEFAULT NULL,
              `metadata_json` text DEFAULT NULL,
              `created_at` datetime DEFAULT current_timestamp(),
              `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_admin_notification` (`admin_id`, `notification_id`),
              KEY `idx_admin_unread` (`admin_id`, `is_read`),
              KEY `idx_type` (`notification_type`),
              KEY `idx_priority` (`priority`),
              KEY `idx_category` (`category`),
              KEY `idx_created_at` (`created_at`),
              KEY `idx_admin_priority` (`admin_id`, `priority`, `is_read`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $sampleNotifications = [
            [
                'type' => 'system_info',
                'title' => 'Welcome to Notification System',
                'message' => 'The notification system has been successfully set up. You will receive alerts about security events, user activities, and system updates here.',
                'priority' => 'normal',
                'category' => 'system'
            ],
            [
                'type' => 'security_info',
                'title' => 'Security Features Active',
                'message' => 'All security monitoring features are active. You will be notified of suspicious activities.',
                'priority' => 'high',
                'category' => 'security'
            ],
            [
                'type' => 'system_ready',
                'title' => 'Dashboard Ready',
                'message' => 'Your admin dashboard is now fully operational. Monitor users, files, and security events from here.',
                'priority' => 'low',
                'category' => 'system'
            ]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO admin_notifications (
                admin_id, notification_id, notification_type,
                title, message, priority, category
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($sampleNotifications as $notif) {
            try {
                $stmt->execute([
                    $adminId,
                    uniqid('notif_', true),
                    $notif['type'],
                    $notif['title'],
                    $notif['message'],
                    $notif['priority'],
                    $notif['category']
                ]);
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    if ($method === 'GET') {
        switch ($action) {
            case 'count':
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM admin_notifications
                    WHERE admin_id = ? AND is_read = 0 AND dismissed = 0
                ");
                $stmt->execute([$adminId]);
                $result = $stmt->fetch();
                respond(200, ['ok' => true, 'unread_count' => (int)$result['count']]);

            case 'summary':
                $stmt = $pdo->prepare("
                    SELECT priority, category, COUNT(*) as count
                    FROM admin_notifications
                    WHERE admin_id = ? AND is_read = 0 AND dismissed = 0
                    GROUP BY priority, category
                    ORDER BY FIELD(priority, 'urgent', 'high', 'normal', 'low'), category
                ");
                $stmt->execute([$adminId]);
                $summary = $stmt->fetchAll();
                respond(200, ['ok' => true, 'summary' => $summary]);

            default:
                $filter = $_GET['filter'] ?? 'all';
                $category = $_GET['category'] ?? '';
                $priority = $_GET['priority'] ?? '';
                $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
                $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

                $where = ["admin_id = ?"];
                $params = [$adminId];

                if ($filter === 'unread') $where[] = "is_read = 0";
                if ($category !== '') { $where[] = "category = ?"; $params[] = $category; }
                if ($priority !== '') { $where[] = "priority = ?"; $params[] = $priority; }
                $where[] = "dismissed = 0";

                $whereClause = implode(' AND ', $where);

                $countSql = "SELECT COUNT(*) as total FROM admin_notifications WHERE " . $whereClause;
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
                $total = (int)$countStmt->fetch()['total'];

                $listSql = "
                    SELECT
                        id, notification_id, notification_type, title, message,
                        priority, category, is_read, dismissed, action_url,
                        metadata_json, created_at, updated_at
                    FROM admin_notifications
                    WHERE " . $whereClause . "
                    ORDER BY
                        CASE priority
                            WHEN 'urgent' THEN 1
                            WHEN 'high' THEN 2
                            WHEN 'normal' THEN 3
                            WHEN 'low' THEN 4
                            ELSE 5
                        END,
                        created_at DESC
                    LIMIT {$limit} OFFSET {$offset}
                ";

                $stmt = $pdo->prepare($listSql);
                $stmt->execute($params);
                $notifications = $stmt->fetchAll();

                foreach ($notifications as &$notif) {
                    $notif['metadata'] = !empty($notif['metadata_json'])
                        ? json_decode($notif['metadata_json'], true)
                        : null;
                    unset($notif['metadata_json']);
                    $notif['is_read'] = (bool)$notif['is_read'];
                    $notif['dismissed'] = (bool)$notif['dismissed'];
                }

                respond(200, [
                    'ok' => true,
                    'notifications' => $notifications,
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset
                ]);
        }
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            respond(400, ['ok' => false, 'error' => 'Invalid JSON in request body']);
        }

        switch ($action) {
            case 'mark_read':
                $notificationIds = $input['notification_ids'] ?? [];
                if (empty($notificationIds)) respond(400, ['ok' => false, 'error' => 'No notification IDs provided']);

                $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
                $stmt = $pdo->prepare("
                    UPDATE admin_notifications
                    SET is_read = 1, updated_at = NOW()
                    WHERE admin_id = ? AND id IN ({$placeholders})
                ");
                $params = array_merge([$adminId], $notificationIds);
                $stmt->execute($params);
                respond(200, ['ok' => true, 'updated' => $stmt->rowCount()]);

            case 'mark_all_read':
                $stmt = $pdo->prepare("
                    UPDATE admin_notifications
                    SET is_read = 1, updated_at = NOW()
                    WHERE admin_id = ? AND is_read = 0
                ");
                $stmt->execute([$adminId]);
                respond(200, ['ok' => true, 'updated' => $stmt->rowCount()]);

            case 'dismiss':
                $notificationIds = $input['notification_ids'] ?? [];
                if (empty($notificationIds)) respond(400, ['ok' => false, 'error' => 'No notification IDs provided']);

                $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
                $stmt = $pdo->prepare("
                    UPDATE admin_notifications
                    SET dismissed = 1, updated_at = NOW()
                    WHERE admin_id = ? AND id IN ({$placeholders})
                ");
                $params = array_merge([$adminId], $notificationIds);
                $stmt->execute($params);
                respond(200, ['ok' => true, 'dismissed' => $stmt->rowCount()]);

            case 'create':
                $notificationType = $input['notification_type'] ?? 'system_alert';
                $title = $input['title'] ?? '';
                $message = $input['message'] ?? '';
                $priority = $input['priority'] ?? 'normal';
                $category = $input['category'] ?? 'system';
                $actionUrl = $input['action_url'] ?? null;
                $metadata = $input['metadata'] ?? null;

                if ($title === '' || $message === '') respond(400, ['ok' => false, 'error' => 'Title and message required']);

                $notificationId = uniqid('notif_', true);

                $stmt = $pdo->prepare("
                    INSERT INTO admin_notifications (
                        admin_id, notification_id, notification_type,
                        title, message, priority, category,
                        action_url, metadata_json
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $adminId,
                    $notificationId,
                    $notificationType,
                    $title,
                    $message,
                    $priority,
                    $category,
                    $actionUrl,
                    $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null
                ]);

                respond(201, [
                    'ok' => true,
                    'notification_id' => $notificationId,
                    'id' => (int)$pdo->lastInsertId()
                ]);

            default:
                respond(400, ['ok' => false, 'error' => 'Invalid action']);
        }
    }

    if ($method === 'DELETE') {
        $notificationIds = $_GET['ids'] ?? '';
        if ($notificationIds === '') respond(400, ['ok' => false, 'error' => 'No notification IDs provided']);

        $ids = array_map('intval', explode(',', $notificationIds));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $pdo->prepare("
            DELETE FROM admin_notifications
            WHERE admin_id = ? AND id IN ({$placeholders})
        ");

        $params = array_merge([$adminId], $ids);
        $stmt->execute($params);

        respond(200, ['ok' => true, 'deleted' => $stmt->rowCount()]);
    }

    respond(400, ['ok' => false, 'error' => 'Invalid request method']);

} catch (PDOException $e) {
    error_log("Notifications API - PDO error: " . $e->getMessage());
    respond(500, ['ok' => false, 'error' => 'Database error']);
} catch (Throwable $e) {
    error_log("Notifications API - Error: " . $e->getMessage());
    respond(500, ['ok' => false, 'error' => 'Server error']);
}
