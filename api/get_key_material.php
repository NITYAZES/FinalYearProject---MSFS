<?php
declare(strict_types=1);



session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Only allow GET requests
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Method not allowed. Use GET.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'message' => 'Not authenticated. Please log in first.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'unknown';
$role = $_SESSION['role'] ?? 'user';

error_log("Key Material Request - user_id: {$userId}, username: {$username}, role: {$role}");

// Admin users don't need key material (they don't have encrypted data)
if ($role === 'admin') {
    error_log("Key Material Request - Admin user, no key material needed");
    echo json_encode([
        'ok' => true,
        'message' => 'Admin users do not require key material',
        'keyMaterial' => null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Optional: Enforce session freshness (e.g., login within last 5 minutes)
$sessionFreshnessMinutes = 5;
$loginTime = $_SESSION['login_time'] ?? 0;
$sessionAge = time() - $loginTime;

if ($sessionAge > ($sessionFreshnessMinutes * 60)) {
    error_log("Key Material Request - Session too old ({$sessionAge}s), requires recent login");
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Session expired. Please log in again to access encryption keys.',
        'requires_reauth' => true
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();
    
    // Fetch crypto keys from user_crypto_keys table
    $stmtKeys = $pdo->prepare('
        SELECT private_key_enc, private_key_iv
        FROM user_crypto_keys
        WHERE user_id = :user_id AND key_status = "active"
        LIMIT 1
    ');
    $stmtKeys->execute(['user_id' => $userId]);
    $cryptoKeys = $stmtKeys->fetch(PDO::FETCH_ASSOC);

    // Fetch KEK from user_kek table
    $stmtKek = $pdo->prepare('
        SELECT kek_enc, kek_iv, pwkdf_salt, pwkdf_iterations
        FROM user_kek
        WHERE user_id = :user_id AND is_active = 1
        LIMIT 1
    ');
    $stmtKek->execute(['user_id' => $userId]);
    $kekData = $stmtKek->fetch(PDO::FETCH_ASSOC);

    // Check if both key sets exist
    if (!$cryptoKeys || !$kekData) {
        error_log("Key Material Request - Missing key data for user_id: {$userId}");
        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'message' => 'Encryption keys not found. Please contact support.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Prepare key material
    $keyMaterial = [
        'pwkdf_salt' => base64_encode($kekData['pwkdf_salt']),
        'pwkdf_iterations' => (int)$kekData['pwkdf_iterations'],
        'kek_enc' => base64_encode($kekData['kek_enc']),
        'kek_iv' => base64_encode($kekData['kek_iv']),
        'private_key_enc' => base64_encode($cryptoKeys['private_key_enc']),
        'private_key_iv' => base64_encode($cryptoKeys['private_key_iv'])
    ];

    // Log key material access
    try {
        $logStmt = $pdo->prepare('
            INSERT INTO security_audit_log 
            (user_id, event_type, event_category, severity, description, ip_address, user_agent)
            VALUES (:user_id, "key_material_accessed", "security", "info", "User retrieved encryption key material", :ip, :ua)
        ');
        $logStmt->execute([
            ':user_id' => $userId,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512)
        ]);
    } catch (Throwable $e) {
        error_log('Failed to log key material access: ' . $e->getMessage());
    }

    error_log("Key Material Request - Successfully retrieved for user_id: {$userId}");

    echo json_encode([
        'ok' => true,
        'message' => 'Key material retrieved successfully',
        'keyMaterial' => $keyMaterial
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Key Material Request Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'An error occurred while retrieving key material'
    ], JSON_UNESCAPED_UNICODE);
}