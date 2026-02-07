<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Block browser back/forward cache after logout
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Enable error logging (do not display)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function json_out(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Prevent CSV injection in Excel/Sheets:
 * If a value begins with =, +, -, @ then prefix with a single quote.
 */
function csv_safe($v): string {
    $s = (string)$v;
    if ($s !== '' && preg_match('/^[=\-+@]/', $s)) {
        return "'" . $s;
    }
    return $s;
}

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    empty($_SESSION['logged_in']) ||
    empty($_SESSION['user_id']) ||
    (($_SESSION['role'] ?? '') !== 'admin')
) {
    error_log('Admin Security Audit - Unauthorized access attempt');
    json_out(401, [
        'success' => false,
        'error' => 'Unauthorized. Admin access required.'
    ]);
}

// Database configuration (same as dashboard_admin.php)
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'multimediasecurefilesharing';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_DSN  = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

try {
    $pdo = new PDO($DB_DSN, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    json_out(500, [
        'success' => false,
        'error' => 'Database connection failed'
    ]);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    if (isset($_GET['export'])) {
        handleExport($pdo, (string)$_GET['export']);
    } else {
        handleGetAuditLogs($pdo);
    }
}

json_out(405, [
    'success' => false,
    'error' => 'Method not allowed'
]);

function handleGetAuditLogs(PDO $pdo): void {
    try {
        // Get overview statistics
        $stats = getOverviewStats($pdo);

        // Get all audit logs with user information
        $logs = getAllAuditLogs($pdo);

        // Get category breakdown
        $categoryBreakdown = getCategoryBreakdown($pdo);

        // Get recent user activity
        $recentActivity = getRecentUserActivity($pdo);

        // Get timeline data for chart
        $timelineData = getTimelineData($pdo);

        json_out(200, [
            'success' => true,
            'stats' => $stats,
            'logs' => $logs,
            'categoryBreakdown' => $categoryBreakdown,
            'recentActivity' => $recentActivity,
            'timelineData' => $timelineData
        ]);
    } catch (PDOException $e) {
        // ✅ Do NOT leak DB details to client
        error_log('Admin Security Audit - Database error: ' . $e->getMessage());
        json_out(500, [
            'success' => false,
            'error' => 'Database error occurred'
        ]);
    } catch (Throwable $e) {
        // ✅ Do NOT leak internal details to client
        error_log('Admin Security Audit - General error: ' . $e->getMessage());
        json_out(500, [
            'success' => false,
            'error' => 'Failed to fetch audit logs'
        ]);
    }
}

function handleExport(PDO $pdo, string $format): void {
    if ($format !== 'csv') {
        json_out(400, [
            'success' => false,
            'error' => 'Only CSV export is supported'
        ]);
    }

    try {
        $stmt = $pdo->query("
            SELECT
                sal.audit_id,
                sal.created_at,
                u.username,
                u.user_email,
                sal.event_type,
                sal.event_category,
                sal.severity,
                sal.description,
                sal.user_agent
            FROM security_audit_log sal
            LEFT JOIN users u ON sal.user_id = u.user_id
            ORDER BY sal.created_at DESC
            LIMIT 5000
        ");

        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        exportCSV($logs);
    } catch (Throwable $e) {
        error_log('Export error: ' . $e->getMessage());
        json_out(500, [
            'success' => false,
            'error' => 'Export failed'
        ]);
    }
}

function exportCSV(array $logs): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Headers
    fputcsv($output, [
        'Audit ID',
        'Timestamp',
        'Username',
        'Email',
        'Event Type',
        'Category',
        'Severity',
        'Description',
        'User Agent'
    ]);

    // Data rows (✅ CSV injection safe)
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['audit_id'],
            $log['created_at'],
            csv_safe($log['username'] ?? 'System'),
            csv_safe($log['user_email'] ?? 'N/A'),
            csv_safe($log['event_type'] ?? ''),
            csv_safe($log['event_category'] ?? ''),
            csv_safe($log['severity'] ?? ''),
            csv_safe($log['description'] ?? ''),
            csv_safe($log['user_agent'] ?? '')
        ]);
    }

    fclose($output);
    exit;
}

function getOverviewStats(PDO $pdo): array {
    try {
        // Total events in last 7 days
        $stmt = $pdo->query("
            SELECT COUNT(*) as total
            FROM security_audit_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $totalEvents = (int)$stmt->fetchColumn();

        // Critical events
        $stmt = $pdo->query("
            SELECT COUNT(*) as total
            FROM security_audit_log
            WHERE severity = 'critical'
        ");
        $criticalEvents = (int)$stmt->fetchColumn();

        // Failed logins in last 24 hours
        $stmt = $pdo->query("
            SELECT COUNT(*) as total
            FROM security_audit_log
            WHERE event_type LIKE '%login_failed%'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $failedLogins = (int)$stmt->fetchColumn();

        // 2FA events
        $stmt = $pdo->query("
            SELECT COUNT(*) as total
            FROM security_audit_log
            WHERE event_type LIKE '%2fa%' OR event_type LIKE '%totp%'
        ");
        $twoFAEvents = (int)$stmt->fetchColumn();

        // Active users (logged in last 24 hours)
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT user_id) as total
            FROM security_audit_log
            WHERE event_type LIKE '%login%'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
              AND user_id IS NOT NULL
        ");
        $activeUsers = (int)$stmt->fetchColumn();

        return [
            'totalEvents' => $totalEvents,
            'criticalEvents' => $criticalEvents,
            'failedLogins' => $failedLogins,
            'twoFAEvents' => $twoFAEvents,
            'activeUsers' => $activeUsers
        ];
    } catch (PDOException $e) {
        error_log('Error in getOverviewStats: ' . $e->getMessage());
        return [
            'totalEvents' => 0,
            'criticalEvents' => 0,
            'failedLogins' => 0,
            'twoFAEvents' => 0,
            'activeUsers' => 0
        ];
    }
}

function getAllAuditLogs(PDO $pdo): array {
    try {
        $stmt = $pdo->query("
            SELECT
                sal.audit_id,
                sal.user_id,
                sal.event_type,
                sal.event_category,
                sal.severity,
                sal.description,
                sal.user_agent,
                sal.metadata_json,
                sal.created_at,
                u.username,
                u.user_email
            FROM security_audit_log sal
            LEFT JOIN users u ON sal.user_id = u.user_id
            ORDER BY sal.created_at DESC
            LIMIT 1000
        ");

        $logs = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $logs[] = [
                'audit_id' => (int)$row['audit_id'],
                'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
                'username' => $row['username'] ?? 'System',
                'user_email' => $row['user_email'] ?? 'N/A',
                'event_type' => $row['event_type'] ?? '',
                'event_category' => $row['event_category'] ?? 'unknown',
                'severity' => $row['severity'] ?? 'info',
                'description' => $row['description'] ?? '',
                'user_agent' => $row['user_agent'] ?? 'N/A',
                'metadata_json' => $row['metadata_json'] ?? null,
                'created_at' => $row['created_at'] ?? ''
            ];
        }

        return $logs;
    } catch (PDOException $e) {
        error_log('Error in getAllAuditLogs: ' . $e->getMessage());
        return [];
    }
}

function getCategoryBreakdown(PDO $pdo): array {
    try {
        $stmt = $pdo->query("
            SELECT
                event_category,
                COUNT(*) as total,
                SUM(CASE WHEN severity = 'info' THEN 1 ELSE 0 END) as info_count,
                SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) as warning_count,
                SUM(CASE WHEN severity = 'error' THEN 1 ELSE 0 END) as error_count,
                SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count
            FROM security_audit_log
            GROUP BY event_category
            ORDER BY total DESC
        ");

        $breakdown = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $breakdown[] = [
                'category' => $row['event_category'] ?? 'unknown',
                'total' => (int)$row['total'],
                'info' => (int)$row['info_count'],
                'warning' => (int)$row['warning_count'],
                'error' => (int)$row['error_count'],
                'critical' => (int)$row['critical_count']
            ];
        }

        return $breakdown;
    } catch (PDOException $e) {
        error_log('Error in getCategoryBreakdown: ' . $e->getMessage());
        return [];
    }
}

function getRecentUserActivity(PDO $pdo): array {
    try {
        $stmt = $pdo->query("
            SELECT
                u.user_id,
                u.username,
                u.user_email,
                u.role,
                u.status,
                u.totp_enabled,
                COUNT(sal.audit_id) as event_count,
                MAX(sal.created_at) as last_activity,
                SUM(CASE WHEN sal.event_type LIKE '%login%' THEN 1 ELSE 0 END) as login_count,
                SUM(CASE WHEN sal.event_type LIKE '%file%' THEN 1 ELSE 0 END) as file_count
            FROM users u
            LEFT JOIN security_audit_log sal ON u.user_id = sal.user_id
                AND sal.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            WHERE u.role = 'user'
            GROUP BY u.user_id
            ORDER BY last_activity DESC
            LIMIT 50
        ");

        $activity = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $activity[] = [
                'user_id' => (int)$row['user_id'],
                'username' => $row['username'] ?? 'Unknown',
                'user_email' => $row['user_email'] ?? 'N/A',
                'role' => $row['role'] ?? 'user',
                'status' => $row['status'] ?? 'active',
                'totp_enabled' => (bool)$row['totp_enabled'],
                'event_count' => (int)$row['event_count'],
                'login_count' => (int)$row['login_count'],
                'file_count' => (int)$row['file_count'],
                'last_activity' => $row['last_activity'] ?? 'Never'
            ];
        }

        return $activity;
    } catch (PDOException $e) {
        error_log('Error in getRecentUserActivity: ' . $e->getMessage());
        return [];
    }
}

function getTimelineData(PDO $pdo): array {
    try {
        $stmt = $pdo->query("
            SELECT
                DATE(created_at) as date,
                severity,
                COUNT(*) as count
            FROM security_audit_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at), severity
            ORDER BY date ASC
        ");

        $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process into timeline format
        $dateMap = [];

        foreach ($rawData as $row) {
            $date = (string)$row['date'];
            if (!isset($dateMap[$date])) {
                $dateMap[$date] = [
                    'date' => $date,
                    'info' => 0,
                    'warning' => 0,
                    'error' => 0,
                    'critical' => 0,
                    'total' => 0
                ];
            }

            $severity = (string)$row['severity'];
            $count = (int)$row['count'];

            if (!isset($dateMap[$date][$severity])) {
                // ignore unexpected severity values
                continue;
            }

            $dateMap[$date][$severity] = $count;
            $dateMap[$date]['total'] += $count;
        }

        // Fill in missing dates with zeros
        $timeline = [];
        $startDate = new DateTime('-30 days');
        $endDate = new DateTime();

        for ($date = clone $startDate; $date <= $endDate; $date->modify('+1 day')) {
            $dateStr = $date->format('Y-m-d');
            $timeline[] = $dateMap[$dateStr] ?? [
                'date' => $dateStr,
                'info' => 0,
                'warning' => 0,
                'error' => 0,
                'critical' => 0,
                'total' => 0
            ];
        }

        return $timeline;
    } catch (PDOException $e) {
        error_log('Error in getTimelineData: ' . $e->getMessage());
        return [];
    }
}
