<?php
declare(strict_types=1);

// Production-safe errors (log only)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

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
        respond(401, [
            'ok' => false,
            'error' => 'Not logged in. Please login first.'
        ]);
    }

    if (($_SESSION['role'] ?? '') !== 'admin') {
        respond(403, [
            'ok' => false,
            'error' => 'Admin access required.'
        ]);
    }

    // Database connection (kept as your original approach)
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

    // Fetch stats
    $totalUsers = (int)$pdo->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
    $activeUsers = (int)$pdo->query("SELECT COUNT(*) c FROM users WHERE status='active'")->fetch()['c'];
    $twoFAEnabled = (int)$pdo->query("SELECT COUNT(*) c FROM users WHERE totp_enabled=1")->fetch()['c'];
    $totalFiles = (int)$pdo->query("SELECT COUNT(*) c FROM shared_files")->fetch()['c'];
    $avgEncryption = (float)$pdo->query("SELECT COALESCE(AVG(encryption_score),0) a FROM encryption_metrics")->fetch()['a'];

    // Fetch users
    $usersFull = $pdo->query("
        SELECT user_id, user_fullname, user_email, user_phone, username, status, totp_enabled, created_at, role
        FROM users
        ORDER BY created_at DESC
    ")->fetchAll();

    // Fetch files
    $filesStmt = $pdo->prepare("
        SELECT
            f.file_id, f.file_name, f.file_size, f.sender_id, f.receiver_id, f.status,
            f.expiry_time, f.uploaded_at, f.mime_type,
            s.user_fullname AS sender_name, r.user_fullname AS receiver_name,
            COALESCE(em.encryption_score, 0) as encryption_score,
            COALESCE(em.encryption_rating, 'Unknown') as encryption_rating
        FROM shared_files f
        LEFT JOIN users s ON s.user_id = f.sender_id
        LEFT JOIN users r ON r.user_id = f.receiver_id
        LEFT JOIN encryption_metrics em ON em.file_id = f.file_id
        ORDER BY f.uploaded_at DESC
    ");
    $filesStmt->execute();
    $filesFull = $filesStmt->fetchAll();

    // Fetch audit logs
    $auditStmt = $pdo->prepare("
        SELECT
            a.audit_id, a.user_id, COALESCE(u.username, 'System') as username,
            a.event_type, a.event_category, a.severity, a.description,
            a.created_at
        FROM security_audit_log a
        LEFT JOIN users u ON u.user_id = a.user_id
        ORDER BY a.created_at DESC
        LIMIT 1000
    ");
    $auditStmt->execute();
    $auditFull = $auditStmt->fetchAll();

    $recentUsers = array_slice($usersFull, 0, 5);
    $recentFiles = array_slice($filesFull, 0, 5);
    $recentAudit = array_slice($auditFull, 0, 10);

    respond(200, [
        'ok' => true,
        'stats' => [
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'twoFAEnabled' => $twoFAEnabled,
            'totalFiles' => $totalFiles,
            'avgEncryption' => round($avgEncryption, 2),
        ],
        'recent_users' => $recentUsers,
        'recent_files' => $recentFiles,
        'recent_activity' => $recentAudit,
        'users' => $usersFull,
        'files' => $filesFull,
        'audit' => $auditFull,
    ]);

} catch (PDOException $e) {
    error_log("Dashboard Admin - PDO error: " . $e->getMessage());
    respond(500, [
        'ok' => false,
        'error' => 'Database error'
    ]);
} catch (Throwable $e) {
    error_log("Dashboard Admin - Error: " . $e->getMessage());
    respond(500, [
        'ok' => false,
        'error' => 'Server error'
    ]);
}
