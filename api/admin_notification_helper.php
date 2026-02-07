<?php


declare(strict_types=1);


function createAdminNotification(
    PDO $pdo,
    string $notificationType,
    string $title, 
    string $message,
    string $priority = 'normal',
    string $category = 'system',
    ?string $actionUrl = null,
    ?array $metadata = null
): bool {
    try {
        // Get all admin user IDs
        $stmt = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' AND status = 'active'");
        $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($admins)) {
            error_log('Warning: No active admin users found for notification');
            return false;
        }

        $baseNotificationId = uniqid('notif_', true);
        
        $insertStmt = $pdo->prepare("
            INSERT INTO admin_notifications (
                admin_id, notification_id, notification_type, 
                title, message, priority, category, 
                action_url, metadata_json, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        // Create notification for each admin
        $successCount = 0;
        foreach ($admins as $adminId) {
            $uniqueNotifId = $baseNotificationId . '_' . $adminId;
            
            $result = $insertStmt->execute([
                $adminId,
                $uniqueNotifId,
                $notificationType,
                $title,
                $message,
                $priority,
                $category,
                $actionUrl,
                $metadata ? json_encode($metadata) : null
            ]);
            
            if ($result) {
                $successCount++;
            }
        }

        error_log("✅ Created admin notification: '{$title}' for {$successCount} admin(s)");
        return $successCount > 0;
        
    } catch (Exception $e) {
        error_log("❌ Failed to create admin notification: " . $e->getMessage());
        return false;
    }
}

// =============================================================================
// USER REGISTRATION EVENTS
// =============================================================================

/**
 * Triggered when a new user registers
 */
function notifyAdminUserRegistered(PDO $pdo, array $userData): void {
    createAdminNotification(
        $pdo,
        'user_registered',
        '👤 New User Registration',
        "New user '{$userData['username']}' ({$userData['email']}) has registered to the system.",
        'normal',
        'user',
        'admin_manage_users.html',
        [
            'user_id' => $userData['user_id'],
            'username' => $userData['username'],
            'email' => $userData['email'],
            'phone' => $userData['phone'] ?? null
        ]
    );
}

/**
 * Triggered when a user verifies their email
 */
function notifyAdminEmailVerified(PDO $pdo, int $userId, string $username, string $email): void {
    createAdminNotification(
        $pdo,
        'email_verified',
        '✅ Email Verified',
        "User '{$username}' ({$email}) has successfully verified their email address.",
        'low',
        'user',
        'admin_manage_users.html',
        ['user_id' => $userId, 'username' => $username, 'email' => $email]
    );
}

// =============================================================================
// LOGIN & SECURITY EVENTS
// =============================================================================

/**
 * Triggered on failed login attempts (3 or more)
 */
function notifyAdminFailedLoginAttempts(PDO $pdo, string $username, int $userId, int $attempts, string $ip): void {
    $priority = $attempts >= 5 ? 'urgent' : ($attempts >= 3 ? 'high' : 'normal');
    
    createAdminNotification(
        $pdo,
        'security_failed_login',
        '🔒 Multiple Failed Login Attempts',
        "User '{$username}' has {$attempts} failed login attempts from IP: {$ip}",
        $priority,
        'security',
        'admin_security_audit.html',
        [
            'user_id' => $userId,
            'username' => $username,
            'attempts' => $attempts,
            'ip_address' => $ip
        ]
    );
}

/**
 * Triggered when an account is locked
 */
function notifyAdminAccountLocked(PDO $pdo, int $userId, string $username, string $reason): void {
    createAdminNotification(
        $pdo,
        'security_account_locked',
        '⚠️ User Account Locked',
        "Account '{$username}' has been locked. Reason: {$reason}",
        'high',
        'security',
        'admin_manage_users.html',
        ['user_id' => $userId, 'username' => $username, 'reason' => $reason]
    );
}

/**
 * Triggered when user enables/disables 2FA
 */
function notifyAdmin2FAStatusChanged(PDO $pdo, int $userId, string $username, bool $enabled): void {
    $action = $enabled ? 'enabled' : 'disabled';
    $emoji = $enabled ? '✅' : '⚠️';
    
    createAdminNotification(
        $pdo,
        'user_2fa_changed',
        "{$emoji} 2FA Status Changed",
        "User '{$username}' has {$action} Two-Factor Authentication.",
        $enabled ? 'low' : 'normal',
        'security',
        'admin_manage_users.html',
        ['user_id' => $userId, 'username' => $username, '2fa_enabled' => $enabled]
    );
}

// =============================================================================
// FILE UPLOAD & DOWNLOAD EVENTS
// =============================================================================

/**
 * Triggered when a file is uploaded (RENAMED to avoid conflict)
 */
function notifyAdminFileUploaded(PDO $pdo, array $fileData): void {
    $sizeMB = round($fileData['file_size'] / (1024 * 1024), 2);
    
    // Only notify for large files (>50MB) to avoid spam
    if ($sizeMB > 50) {
        createAdminNotification(
            $pdo,
            'file_large_upload',
            '📁 Large File Uploaded',
            "User '{$fileData['sender_name']}' uploaded a large file: {$fileData['file_name']} ({$sizeMB}MB) to {$fileData['receiver_name']}",
            'normal',
            'file',
            'admin_manage_files.html',
            [
                'file_id' => $fileData['file_id'],
                'sender_id' => $fileData['sender_id'],
                'receiver_id' => $fileData['receiver_id'],
                'size_mb' => $sizeMB
            ]
        );
    }
}

/**
 * Triggered when download limit is reached (RENAMED to avoid conflict)
 */
function notifyAdminDownloadLimitReached(PDO $pdo, array $fileData): void {
    createAdminNotification(
        $pdo,
        'file_download_limit_reached',
        '📊 Download Limit Reached',
        "File '{$fileData['file_name']}' has reached its download limit ({$fileData['max_downloads']} downloads)",
        'low',
        'file',
        'admin_manage_files.html',
        [
            'file_id' => $fileData['file_id'],
            'sender_id' => $fileData['sender_id'],
            'downloads' => $fileData['max_downloads']
        ]
    );
}

/**
 * Triggered when file expires (RENAMED to avoid conflict)
 */
function notifyAdminFileExpired(PDO $pdo, string $fileId, string $fileName, string $senderName): void {
    createAdminNotification(
        $pdo,
        'file_expired',
        '⏰ File Expired',
        "File '{$fileName}' from user '{$senderName}' has expired and been removed from the system.",
        'low',
        'file',
        'admin_manage_files.html',
        ['file_id' => $fileId, 'file_name' => $fileName]
    );
}

/**
 * Triggered on bulk downloads (potential data exfiltration)
 */
function notifyAdminBulkDownload(PDO $pdo, int $userId, string $username, int $fileCount, float $totalSizeMB): void {
    createAdminNotification(
        $pdo,
        'security_bulk_download',
        '⬇️ Suspicious Bulk Download Detected',
        "User '{$username}' downloaded {$fileCount} files ({$totalSizeMB}MB total) in a short period. This may indicate data exfiltration.",
        'high',
        'security',
        'admin_security_audit.html',
        [
            'user_id' => $userId,
            'username' => $username,
            'file_count' => $fileCount,
            'size_mb' => $totalSizeMB
        ]
    );
}

// =============================================================================
// USER MANAGEMENT EVENTS
// =============================================================================

/**
 * Triggered when a user account is deleted
 */
function notifyAdminUserDeleted(PDO $pdo, string $username, int $deletedByAdminId): void {
    createAdminNotification(
        $pdo,
        'user_deleted',
        '🗑️ User Account Deleted',
        "User account '{$username}' was permanently deleted from the system.",
        'normal',
        'user',
        'admin_manage_users.html',
        ['deleted_username' => $username, 'deleted_by_admin_id' => $deletedByAdminId]
    );
}

/**
 * Triggered when password reset is requested
 */
function notifyAdminPasswordResetRequested(PDO $pdo, int $userId, string $username, string $email): void {
    createAdminNotification(
        $pdo,
        'user_password_reset',
        '🔑 Password Reset Requested',
        "User '{$username}' ({$email}) has requested a password reset.",
        'low',
        'user',
        null,
        ['user_id' => $userId, 'username' => $username, 'email' => $email]
    );
}

// =============================================================================
// SYSTEM EVENTS
// =============================================================================

/**
 * Triggered on system errors
 */
function notifyAdminSystemError(PDO $pdo, string $errorType, string $message, ?array $context = null): void {
    createAdminNotification(
        $pdo,
        'system_error',
        '❌ System Error Occurred',
        "System error: {$errorType} - {$message}",
        'high',
        'system',
        null,
        array_merge(
            ['error_type' => $errorType, 'timestamp' => date('Y-m-d H:i:s')],
            $context ?? []
        )
    );
}

/**
 * Triggered when database backup completes
 */
function notifyAdminDatabaseBackup(PDO $pdo, bool $success, ?string $backupPath = null, ?float $sizeMB = null): void {
    if ($success) {
        createAdminNotification(
            $pdo,
            'system_backup_success',
            '💾 Database Backup Completed',
            "Automated database backup completed successfully." . ($sizeMB ? " Size: {$sizeMB}MB" : ""),
            'low',
            'system',
            null,
            ['backup_path' => $backupPath, 'size_mb' => $sizeMB]
        );
    } else {
        createAdminNotification(
            $pdo,
            'system_backup_failed',
            '⚠️ Database Backup Failed',
            "Automated database backup failed. Immediate attention required!",
            'urgent',
            'system',
            null,
            ['backup_path' => $backupPath]
        );
    }
}

/**
 * Triggered when suspicious activity is detected
 */
function notifyAdminSuspiciousActivity(PDO $pdo, string $activity, int $userId, string $username, array $details): void {
    createAdminNotification(
        $pdo,
        'security_suspicious',
        '🚨 Suspicious Activity Detected',
        "Suspicious activity detected: {$activity} by user '{$username}'",
        'urgent',
        'security',
        'admin_security_audit.html',
        array_merge($details, ['user_id' => $userId, 'username' => $username])
    );
}

// =============================================================================
// HELPER FUNCTION: Format file size
// =============================================================================

function formatBytesForNotification(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}

// =============================================================================
// Backward-compatible aliases (older function names used in other scripts)
// =============================================================================

/**
 * Older name used in register.php
 * Canonical: notifyAdminUserRegistered()
 */
function notifyUserRegistered(PDO $pdo, array $userData): void
{
    notifyAdminUserRegistered($pdo, $userData);
}

/**
 * Older name used in index.php
 * Canonical: notifyAdminFailedLoginAttempts()
 */
function notifyFailedLoginAttempts(PDO $pdo, string $username, int $userId, int $attempts, string $ip): void
{
    notifyAdminFailedLoginAttempts($pdo, $username, $userId, $attempts, $ip);
}

/**
 * Older name used in index.php
 * Canonical: notifyAdminAccountLocked()
 */
function notifyAccountLocked(PDO $pdo, int $userId, string $username, string $reason): void
{
    notifyAdminAccountLocked($pdo, $userId, $username, $reason);
}
