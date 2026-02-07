<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// ============= CONFIGURATION =============
$ADMIN_USERNAME = 'Admin01';
$ADMIN_PASSWORD = 'Admin01@200101';      
$ADMIN_EMAIL    = 'admin01@gmail.com';   
$ADMIN_PHONE    = '+60123456789';
$ADMIN_FULLNAME = 'System Administrator';
// =========================================

echo "🔐 Admin Account Creation Script (PLAINTEXT-Compatible)\n";
echo "======================================================\n\n";

try {
    $pdo = db();

    // Step 1: Check if admin already exists (by username OR any admin role)
    $checkStmt = $pdo->prepare(
        'SELECT user_id, username FROM users WHERE username = :username OR role = "admin" LIMIT 1'
    );
    $checkStmt->execute([':username' => $ADMIN_USERNAME]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "❌ Error: Admin account already exists!\n";
        echo "   Username: {$existing['username']}\n";
        echo "   User ID: {$existing['user_id']}\n\n";
        echo "Delete the existing admin row if you want to recreate it.\n";
        exit(1);
    }

    // Step 2: Hash password EXACTLY like login.php expects (PLAINTEXT -> password_hash)
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    $algoName = defined('PASSWORD_ARGON2ID') ? 'Argon2ID' : 'Bcrypt';

    $finalHash = password_hash($ADMIN_PASSWORD, $algo);
    if ($finalHash === false) {
        throw new Exception('Failed to hash password');
    }

    echo "✅ Step 1: Server-side {$algoName} hash generated (plaintext compatible)\n";

    // Step 3: Insert admin user
    $pdo->beginTransaction();

    $sql = <<<SQL
INSERT INTO users (
    user_fullname,
    user_phone,
    user_email,
    username,
    user_password,
    role,
    status,
    email_verified_at,
    created_at,
    login_attempts,
    account_locked_until
) VALUES (
    :fullname,
    :phone,
    :email,
    :username,
    :password,
    'admin',
    'active',
    NOW(),
    NOW(),
    0,
    NULL
)
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':fullname' => $ADMIN_FULLNAME,
        ':phone'    => $ADMIN_PHONE,
        ':email'    => $ADMIN_EMAIL,
        ':username' => $ADMIN_USERNAME,
        ':password' => $finalHash,
    ]);

    $adminUserId = (int)$pdo->lastInsertId();
    $pdo->commit();

    echo "✅ Step 2: Admin user created (ID: {$adminUserId})\n\n";

    echo "================================\n";
    echo "✅ ADMIN ACCOUNT CREATED SUCCESSFULLY!\n";
    echo "================================\n\n";
    echo "Admin Credentials:\n";
    echo "  Username: {$ADMIN_USERNAME}\n";
    echo "  Password: {$ADMIN_PASSWORD}\n";
    echo "  Email:    {$ADMIN_EMAIL}\n";
    echo "  User ID:  {$adminUserId}\n\n";

    echo "Notes:\n";
    echo "  - This password format matches your current login.php (plaintext -> password_verify).\n";
    echo "  - If TOTP is mandatory in your system, admin will be prompted after login.\n\n";

    echo "⚠️  SECURITY:\n";
    echo "  1. DELETE THIS SCRIPT after use.\n";
    echo "  2. Change admin password after first login.\n\n";

} catch (PDOException $e) {
    if (isset($pdo)) {
        try { $pdo->rollBack(); } catch (Throwable $ignored) {}
    }
    echo "❌ Database Error: {$e->getMessage()}\n";
    exit(1);

} catch (Throwable $e) {
    if (isset($pdo)) {
        try { $pdo->rollBack(); } catch (Throwable $ignored) {}
    }
    echo "❌ Error: {$e->getMessage()}\n";
    exit(1);
}
