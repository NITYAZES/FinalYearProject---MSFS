<?php
declare(strict_types=1);

/**
 * User Notification Helper - Comprehensive event coverage
 * Covers all file operations, security events, and user activities
 *
 * Save this file as: user_notification_helper.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/activity_logger.php';

/* =========================================================
   Time formatting helpers (days/hours/minutes)
   ========================================================= */

/**
 * Return a friendly time remaining string.
 * - < 60 minutes => "X minutes"
 * - < 48 hours   => "X hours"
 * - otherwise    => "X days"
 */
function formatTimeRemaining(int $seconds): string
{
    if ($seconds <= 0) {
        return 'expired';
    }

    $minutes = (int) floor($seconds / 60);
    if ($minutes < 60) {
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }

    $hours = (int) floor($seconds / 3600);
    if ($hours < 48) {
        return $hours . ' hour' . ($hours === 1 ? '' : 's');
    }

    $days = (int) ceil($seconds / 86400);
    return $days . ' day' . ($days === 1 ? '' : 's');
}

/**
 * Convert DB datetime OR unix timestamp to "X days/hours/minutes".
 * Accepts:
 * - unix int/string (e.g., 1700000000)
 * - datetime string (e.g., "2026-01-24 12:00:00")
 */
function formatExpiryRemaining(mixed $expiryTime): string
{
    $expiryTs = null;

    if (is_int($expiryTime) || (is_string($expiryTime) && ctype_digit($expiryTime))) {
        $expiryTs = (int) $expiryTime;
    } elseif (is_string($expiryTime)) {
        $tmp = strtotime($expiryTime);
        if ($tmp !== false) $expiryTs = $tmp;
    }

    if ($expiryTs === null) {
        return 'unknown';
    }

    return formatTimeRemaining($expiryTs - time());
}

/* =========================================================
   Core notification insert
   ========================================================= */

/**
 * Create a user notification with proper error handling
 */
function createUserNotification(
    PDO $pdo,
    int $userId,
    string $notificationId,
    string $notificationType,
    string $priority = 'normal'
): bool {
    try {
        // Prevent duplicate notifications
        $checkStmt = $pdo->prepare('
            SELECT id FROM user_notifications
            WHERE user_id = :user_id
              AND notification_id = :notification_id
            LIMIT 1
        ');

        $checkStmt->execute([
            ':user_id' => $userId,
            ':notification_id' => $notificationId
        ]);

        if ($checkStmt->fetch()) {
            return true; // Already exists
        }

        // Create new notification
        $stmt = $pdo->prepare('
            INSERT INTO user_notifications
            (user_id, notification_id, notification_type, priority, is_read, dismissed, created_at)
            VALUES
            (:user_id, :notification_id, :notification_type, :priority, 0, 0, NOW())
        ');

        $stmt->execute([
            ':user_id' => $userId,
            ':notification_id' => $notificationId,
            ':notification_type' => $notificationType,
            ':priority' => $priority
        ]);

        return true;
    } catch (Throwable $e) {
        error_log('Failed to create user notification: ' . $e->getMessage());
        return false;
    }
}

/* =========================================================
   File upload/download/access events
   ========================================================= */

/**
 * Notify when file is uploaded to recipient
 */
function notifyFileUploaded(PDO $pdo, int $senderId, int $receiverId, string $fileId, string $fileName, int $fileSize): void
{
    try {
        // Notify receiver about new file
        createUserNotification($pdo, $receiverId, 'shared_' . $fileId, 'file_received', 'normal');

        // Notify sender about successful upload
        createUserNotification(
            $pdo,
            $senderId,
            'upload_success_' . $fileId . '_' . time(),
            'file_uploaded_success',
            'low'
        );

        error_log("✅ Notifications created for file upload: {$fileId}");
    } catch (Throwable $e) {
        error_log('Failed to create upload notifications: ' . $e->getMessage());
    }
}

/**
 * Notify when file is accessed/downloaded
 */
function notifyFileAccessed(PDO $pdo, int $senderId, int $receiverId, string $fileId, string $fileName, int $currentCount, int $maxCount): void
{
    try {
        $timestamp = time();

        // Always notify sender when their file is accessed
        createUserNotification($pdo, $senderId, 'accessed_' . $fileId . '_' . $timestamp, 'file_downloaded', 'normal');

        // Notify receiver of successful download
        createUserNotification($pdo, $receiverId, 'download_success_' . $fileId . '_' . $timestamp, 'file_download_success', 'low');

        error_log("✅ File access notifications created for: {$fileId}");
    } catch (Throwable $e) {
        error_log('Failed to create access notifications: ' . $e->getMessage());
    }
}

/**
 * Notify when approaching download limit
 */
function notifyDownloadLimitWarning(PDO $pdo, int $senderId, int $receiverId, string $fileId, string $fileName, int $remaining): void
{
    try {
        $timestamp = time();

        // High priority warning for sender
        createUserNotification($pdo, $senderId, 'download_warning_' . $fileId . '_' . $timestamp, 'download_limit_warning', 'high');

        // Warning for receiver
        createUserNotification($pdo, $receiverId, 'download_warning_receiver_' . $fileId . '_' . $timestamp, 'download_limit_warning', 'high');

        error_log("⚠️ Download limit warning sent for: {$fileId} ({$remaining} remaining)");
    } catch (Throwable $e) {
        error_log('Failed to create warning notifications: ' . $e->getMessage());
    }
}

/**
 * Notify when download limit is reached
 */
function notifyDownloadLimitReached(PDO $pdo, int $senderId, int $receiverId, string $fileId, string $fileName): void
{
    try {
        $timestamp = time();

        createUserNotification($pdo, $senderId, 'download_limit_reached_' . $fileId . '_' . $timestamp, 'download_limit_reached', 'critical');
        createUserNotification($pdo, $receiverId, 'download_limit_reached_receiver_' . $fileId . '_' . $timestamp, 'download_limit_reached', 'critical');

        error_log("🚫 Download limit reached notifications sent for: {$fileId}");
    } catch (Throwable $e) {
        error_log('Failed to create limit reached notifications: ' . $e->getMessage());
    }
}

/* =========================================================
   Expiry events (FIXED: days/hours/minutes)
   ========================================================= */

/**
 * Notify when file is expiring soon (24 hours).
 * NOTE: Uses stable notification IDs => prevents spam duplicates.
 * Also writes an activity message containing: "expires in X days/hours/minutes"
 */
function notifyFileExpiringSoon(PDO $pdo, int $senderId, int $receiverId, string $fileId, string $fileName, mixed $expiryTime): void
{
    try {
        $remaining = formatExpiryRemaining($expiryTime);

        // Sender + receiver warning (stable IDs to avoid duplicates)
        createUserNotification($pdo, $senderId, 'expiring_' . $fileId, 'file_expiring_soon', 'high');
        createUserNotification($pdo, $receiverId, 'expiring_receiver_' . $fileId, 'file_expiring_soon', 'high');

        // Optional: store readable time in activity log (useful for UI)
        logUserActivity($pdo, $senderId, 'file_expiring_soon', "\"{$fileName}\" expires in {$remaining}", null);
        logUserActivity($pdo, $receiverId, 'file_expiring_soon', "\"{$fileName}\" expires in {$remaining}", null);

        error_log("⏰ Expiry warning sent for: {$fileId} (expires in {$remaining})");
    } catch (Throwable $e) {
        error_log('Failed to create expiry notifications: ' . $e->getMessage());
    }
}

/**
 * Notify when file has expired
 */
function notifyFileExpired(PDO $pdo, int $senderId, int $receiverId, string $fileId, string $fileName): void
{
    try {
        $timestamp = time();

        createUserNotification($pdo, $senderId, 'expired_' . $fileId . '_' . $timestamp, 'file_expired', 'normal');
        createUserNotification($pdo, $receiverId, 'expired_receiver_' . $fileId . '_' . $timestamp, 'file_expired', 'normal');

        error_log("⏱️ File expired notifications sent for: {$fileId}");
    } catch (Throwable $e) {
        error_log('Failed to create expiry notifications: ' . $e->getMessage());
    }
}

/* =========================================================
   Policy / access changes
   ========================================================= */

/**
 * Notify when policy is edited
 */
function notifyPolicyEdited(PDO $pdo, int $senderId, array $receiverIds, string $fileId, string $fileName): void
{
    try {
        $timestamp = time();

        foreach ($receiverIds as $receiverId) {
            createUserNotification($pdo, (int)$receiverId, 'policy_changed_' . $fileId . '_' . $timestamp, 'policy_updated', 'high');
        }

        createUserNotification($pdo, $senderId, 'policy_updated_confirm_' . $fileId . '_' . $timestamp, 'policy_updated_success', 'low');

        error_log("📝 Policy update notifications sent for: {$fileId}");
    } catch (Throwable $e) {
        error_log('Failed to create policy notifications: ' . $e->getMessage());
    }
}

/**
 * Notify when recipient is removed
 */
function notifyRecipientRemoved(PDO $pdo, int $senderId, int $removedRecipientId, string $fileId, string $fileName): void
{
    try {
        $timestamp = time();

        createUserNotification($pdo, $removedRecipientId, 'access_revoked_' . $fileId . '_' . $timestamp, 'access_revoked', 'high');
        createUserNotification($pdo, $senderId, 'recipient_removed_' . $fileId . '_' . $timestamp, 'recipient_removed_success', 'low');

        error_log("🚫 Recipient removal notifications sent for: {$fileId}");
    } catch (Throwable $e) {
        error_log('Failed to create removal notifications: ' . $e->getMessage());
    }
}

/**
 * Notify when file access is revoked for all
 */
function notifyFileRevoked(PDO $pdo, int $senderId, array $receiverIds, string $fileId, string $fileName): void
{
    try {
        $timestamp = time();

        foreach ($receiverIds as $receiverId) {
            createUserNotification($pdo, (int)$receiverId, 'file_revoked_' . $fileId . '_' . $timestamp, 'file_access_revoked', 'critical');
        }

        createUserNotification($pdo, $senderId, 'revoke_confirm_' . $fileId . '_' . $timestamp, 'file_revoked_success', 'low');

        error_log("🔒 File revocation notifications sent for: {$fileId}");
    } catch (Throwable $e) {
        error_log('Failed to create revocation notifications: ' . $e->getMessage());
    }
}

/**
 * Notify when file is deleted
 */
function notifyFileDeleted(PDO $pdo, int $senderId, array $receiverIds, string $fileId, string $fileName): void
{
    try {
        $timestamp = time();

        foreach ($receiverIds as $receiverId) {
            createUserNotification($pdo, (int)$receiverId, 'file_deleted_' . $fileId . '_' . $timestamp, 'file_deleted', 'high');
        }

        createUserNotification($pdo, $senderId, 'delete_confirm_' . $fileId . '_' . $timestamp, 'file_deleted_success', 'low');

        error_log("🗑️ File deletion notifications sent for: {$fileId}");
    } catch (Throwable $e) {
        error_log('Failed to create deletion notifications: ' . $e->getMessage());
    }
}

/* =========================================================
   Security alerts
   ========================================================= */

function notifyAccessDenied(PDO $pdo, int $userId, string $fileId, string $fileName, string $reason): void
{
    try {
        $timestamp = time();

        createUserNotification($pdo, $userId, 'access_denied_' . $fileId . '_' . $timestamp, 'access_denied', 'high');

        error_log("⛔ Access denied notification sent for: {$fileId} (reason: {$reason})");
    } catch (Throwable $e) {
        error_log('Failed to create access denied notification: ' . $e->getMessage());
    }
}

function notifyKeyRotation(PDO $pdo, int $userId): void
{
    try {
        $timestamp = time();
        createUserNotification($pdo, $userId, 'key_rotation_' . $timestamp, 'encryption_key_rotated', 'high');
        error_log("🔑 Key rotation notification sent for user: {$userId}");
    } catch (Throwable $e) {
        error_log('Failed to create key rotation notification: ' . $e->getMessage());
    }
}

function notifyPasswordChanged(PDO $pdo, int $userId): void
{
    try {
        $timestamp = time();
        createUserNotification($pdo, $userId, 'password_changed_' . $timestamp, 'password_changed', 'critical');
        error_log("🔐 Password change notification sent for user: {$userId}");
    } catch (Throwable $e) {
        error_log('Failed to create password change notification: ' . $e->getMessage());
    }
}

function notify2FAStatusChanged(PDO $pdo, int $userId, bool $enabled): void
{
    try {
        $timestamp = time();
        $type = $enabled ? '2fa_enabled' : '2fa_disabled';
        $priority = $enabled ? 'normal' : 'critical';

        createUserNotification($pdo, $userId, '2fa_status_' . $timestamp, $type, $priority);

        $status = $enabled ? 'enabled' : 'disabled';
        error_log("🔒 2FA {$status} notification sent for user: {$userId}");
    } catch (Throwable $e) {
        error_log('Failed to create 2FA notification: ' . $e->getMessage());
    }
}

function notifySuspiciousLogin(PDO $pdo, int $userId, string $ipAddress, string $userAgent): void
{
    try {
        $timestamp = time();

        createUserNotification($pdo, $userId, 'suspicious_login_' . $timestamp, 'suspicious_login', 'critical');

        error_log("⚠️ Suspicious login notification sent for user: {$userId} from IP: {$ipAddress}");
    } catch (Throwable $e) {
        error_log('Failed to create suspicious login notification: ' . $e->getMessage());
    }
}

/* =========================================================
   Batch expiry warnings (cron jobs)
   ========================================================= */

function createExpiryWarningBatch(PDO $pdo): int
{
    try {
        $stmt = $pdo->prepare('
            SELECT sf.file_id, sf.file_name, sf.sender_id, sf.receiver_id, sf.expiry_time
            FROM shared_files sf
            WHERE sf.status = "active"
              AND sf.expiry_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
        ');

        $stmt->execute();
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($files as $file) {
            notifyFileExpiringSoon(
                $pdo,
                (int)$file['sender_id'],
                (int)$file['receiver_id'],
                (string)$file['file_id'],
                (string)$file['file_name'],
                $file['expiry_time'] // datetime string
            );
            $count++;
        }

        if ($count > 0) {
            error_log("📅 Created {$count} expiry warning notifications");
        }

        return $count;
    } catch (Throwable $e) {
        error_log('Failed to create expiry warning batch: ' . $e->getMessage());
        return 0;
    }
}
// =============================================================================
// Backward-compatible aliases (older function names used in other scripts)
// =============================================================================

/**
 * Older name used in download.php
 * Canonical: notifyFileExpired()
 */
function notifyExpiredAccess(
    PDO $pdo,
    int $senderId,
    int $receiverId,
    string $fileId,
    string $fileName,
    mixed $expiryTime = null
): void {
    // $expiryTime kept only for compatibility
    notifyFileExpired($pdo, $senderId, $receiverId, $fileId, $fileName);
}

/**
 * Older name used in download.php
 * Canonical: notifyDownloadLimitReached()
 */
function notifyLimitExceeded(
    PDO $pdo,
    int $senderId,
    int $receiverId,
    string $fileId,
    string $fileName,
    mixed $maxDownloads = null
): void {
    // $maxDownloads kept only for compatibility
    notifyDownloadLimitReached($pdo, $senderId, $receiverId, $fileId, $fileName);
}
