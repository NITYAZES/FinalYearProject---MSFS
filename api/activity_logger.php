<?php

declare(strict_types=1);

//Records a user action in the activity log.
 
 
function logUserActivity(
    PDO $pdo,
    int $userId,
    string $activityType,
    ?string $description = null
): bool {
    try {
        // Insert activity record with current timestamp
        $stmt = $pdo->prepare(
            'INSERT INTO user_activity_log 
             (user_id, activity_type, description, created_at)
             VALUES (:user_id, :activity_type, :description, NOW())'
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':activity_type' => $activityType,
            ':description' => $description
        ]);

        return true;
    } catch (Throwable $e) {
        // Log error but do not break application flow
        error_log('Failed to log user activity: ' . $e->getMessage());
        return false;
    }
}


 //Creates a notification for a user.

 
function createNotification(
    PDO $pdo,
    int $userId,
    string $notificationId,
    string $notificationType,
    string $priority = 'normal'
): bool {
    try {
        // Prevent duplicate notifications for the same user
        $checkStmt = $pdo->prepare(
            'SELECT id FROM user_notifications
             WHERE user_id = :user_id 
               AND notification_id = :notification_id
             LIMIT 1'
        );

        $checkStmt->execute([
            ':user_id' => $userId,
            ':notification_id' => $notificationId
        ]);

        // If already exists, treat as success and exit
        if ($checkStmt->fetch()) {
            return true;
        }

        // Create new unread, active notification
        $stmt = $pdo->prepare(
            'INSERT INTO user_notifications
             (user_id, notification_id, notification_type, priority, is_read, dismissed, created_at)
             VALUES
             (:user_id, :notification_id, :notification_type, :priority, 0, 0, NOW())'
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':notification_id' => $notificationId,
            ':notification_type' => $notificationType,
            ':priority' => $priority
        ]);

        return true;
    } catch (Throwable $e) {
        // Fail silently but log for debugging
        error_log('Failed to create notification: ' . $e->getMessage());
        return false;
    }
}
