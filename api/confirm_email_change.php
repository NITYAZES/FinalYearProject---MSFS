<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/config.php';

try {
    if (empty($_GET['token'])) {
        throw new Exception('Missing token');
    }

    $token = (string)$_GET['token'];

    // ✅ Use token verification from config.php (no redeclare here)
    $data = verify_email_change_token($token);

    $pdo = db();
    $userId  = (int)$data['user_id'];
    $newEmail = (string)$data['new_email'];

    // Fetch user
    $stmt = $pdo->prepare("SELECT user_id, status FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    // Ensure email still not taken
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_email = ? AND user_id != ?");
    $stmt->execute([$newEmail, $userId]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Email already in use');
    }

    $pdo->beginTransaction();

    // ✅ Update email ONLY after link clicked
    $sql = "
        UPDATE users
        SET
            user_email = ?,
            email_verified_at = NOW(),
            status = CASE
                WHEN status = 'suspended' THEN status
                ELSE 'active'
            END,
            updated_at = NOW()
        WHERE user_id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$newEmail, $userId]);

    // Audit log
    $log = $pdo->prepare("
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
        (?, 'user_email_verified', 'security', 'info', ?, ?, ?, NOW())
    ");

    $log->execute([
        $userId,
        'User verified new login email',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        json_encode([
            'user_id' => $userId,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE)
    ]);

    $pdo->commit();

    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Email Verified</title>
        <style>
            body { font-family: Arial, sans-serif; background:#f9fafb; text-align:center; padding:60px; }
            .box { background:#fff; max-width:500px; margin:auto; padding:40px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,.1); }
            h1 { color:#16a34a; }
        </style>
    </head>
    <body>
        <div class='box'>
            <h1>✅ Email Verified</h1>
            <p>Your login email has been successfully verified.</p>
            <p>You may now log in using your new email address.</p>
        </div>
    </body>
    </html>";

} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo "<h2>Email verification failed</h2><p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
