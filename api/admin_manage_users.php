<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';

// Error handling
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (
    empty($_SESSION['logged_in']) ||
    empty($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    json_out(401, [
        'success' => false,
        'message' => 'Unauthorized. Admin access required.'
    ]);
}


$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                getAllUsers();
            } elseif ($action === 'details' && isset($_GET['user_id'])) {
                getUserDetails((int)$_GET['user_id']);
            } elseif ($action === 'stats') {
                getUserStatistics();
            } else {
                json_out(400, ['success' => false, 'message' => 'Invalid action']);
            }
            break;

        case 'POST':
            if ($action === 'request_update' || $action === 'verify_update') {
                // ❌ Disabled: would create new table. Kept for compatibility only.
                json_out(400, [
                    'success' => false,
                    'message' => 'This action is disabled (no DB changes allowed). Use action=update.'
                ]);
            } elseif ($action === 'update') {
                $data = json_decode(file_get_contents('php://input'), true);
                if (!is_array($data)) {
                    json_out(400, ['success' => false, 'message' => 'Invalid JSON payload']);
                }
                updateUser($data);
            } else {
                json_out(400, ['success' => false, 'message' => 'Invalid action']);
            }
            break;

        case 'DELETE':
            if ($action === 'delete' && isset($_GET['user_id'])) {
                deleteUser((int)$_GET['user_id']);
            } else {
                json_out(400, ['success' => false, 'message' => 'Invalid action']);
            }
            break;

        default:
            json_out(405, ['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log('Admin Manage Users Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    json_out(500, ['success' => false, 'message' => 'Server error']);
}

function getAllUsers(): void
{
    $pdo = db();

    try {
        $sql = "
            SELECT 
                u.user_id,
                u.user_fullname,
                u.username,
                u.user_email,
                u.user_phone,
                u.role,
                u.status,
                u.totp_enabled,
                u.email_verified_at,
                u.created_at,
                u.updated_at,
                COALESCE((SELECT COUNT(*) FROM shared_files WHERE sender_id = u.user_id), 0) as files_sent,
                COALESCE((SELECT COUNT(*) FROM shared_files WHERE receiver_id = u.user_id), 0) as files_received
            FROM users u
            ORDER BY u.created_at DESC
        ";

        $stmt = $pdo->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as &$user) {
            $user['totp_enabled'] = (bool)$user['totp_enabled'];
            $user['email_verified'] = !is_null($user['email_verified_at']);
            $user['files_sent'] = (int)$user['files_sent'];
            $user['files_received'] = (int)$user['files_received'];
            $user['total_activity'] = $user['files_sent'] + $user['files_received'];
        }

        json_out(200, [
            'success' => true,
            'users' => $users,
            'current_admin_id' => (int)($_SESSION['user_id'] ?? 0)
        ]);
    } catch (PDOException $e) {
        error_log('getAllUsers error: ' . $e->getMessage());
        json_out(500, ['success' => false, 'message' => 'Database error']);
    }
}

function getUserDetails(int $userId): void
{
    $pdo = db();

    try {
        $sql = "
            SELECT 
                u.user_id,
                u.user_fullname,
                u.username,
                u.user_email,
                u.user_phone,
                u.role,
                u.status,
                u.totp_enabled,
                u.email_verified_at,
                u.created_at,
                u.updated_at
            FROM users u
            WHERE u.user_id = ?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            json_out(404, ['success' => false, 'message' => 'User not found']);
            return;
        }

        logAdminAction(
            $pdo,
            'admin_viewed_user_details',
            "Admin viewed details for user '{$user['username']}' (ID: {$userId})",
            [
                'viewed_user_id' => $userId,
                'viewed_username' => $user['username'],
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'info'
        );

        $fileStats = ['files_sent' => 0, 'files_received' => 0, 'storage_used' => 0];

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(file_size), 0) as size FROM shared_files WHERE sender_id = ?");
            $stmt->execute([$userId]);
            $sentData = $stmt->fetch(PDO::FETCH_ASSOC);
            $fileStats['files_sent'] = (int)$sentData['count'];
            $fileStats['storage_used'] = (int)$sentData['size'];

            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM shared_files WHERE receiver_id = ?");
            $stmt->execute([$userId]);
            $receivedData = $stmt->fetch(PDO::FETCH_ASSOC);
            $fileStats['files_received'] = (int)$receivedData['count'];
        } catch (PDOException $e) {
            error_log('File stats query failed: ' . $e->getMessage());
        }

        $user['totp_enabled'] = (bool)$user['totp_enabled'];
        $user['email_verified'] = !is_null($user['email_verified_at']);
        $user['file_stats'] = $fileStats;

        json_out(200, [
            'success' => true,
            'user' => $user
        ]);
    } catch (Exception $e) {
        error_log('getUserDetails error: ' . $e->getMessage());
        json_out(500, ['success' => false, 'message' => 'Error fetching user details']);
    }
}

function getUserStatistics(): void
{
    $pdo = db();

    try {
        $sql = "
            SELECT 
                COUNT(*) as total_users,
                COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as active_users,
                COALESCE(SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END), 0) as inactive_users,
                COALESCE(SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END), 0) as suspended_users,
                COALESCE(SUM(CASE WHEN totp_enabled = 1 THEN 1 ELSE 0 END), 0) as users_with_2fa,
                COALESCE(SUM(CASE WHEN email_verified_at IS NOT NULL THEN 1 ELSE 0 END), 0) as verified_emails,
                COALESCE(SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END), 0) as admin_users,
                COALESCE(SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END), 0) as regular_users
            FROM users
        ";

        $stmt = $pdo->query($sql);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        foreach ($stats as $key => $value) {
            $stats[$key] = (int)$value;
        }

        $total = $stats['total_users'];
        $stats['active_percentage'] = $total > 0 ? round(($stats['active_users'] / $total) * 100, 2) : 0;
        $stats['2fa_percentage'] = $total > 0 ? round(($stats['users_with_2fa'] / $total) * 100, 2) : 0;
        $stats['verified_percentage'] = $total > 0 ? round(($stats['verified_emails'] / $total) * 100, 2) : 0;

        json_out(200, [
            'success' => true,
            'statistics' => $stats
        ]);
    } catch (PDOException $e) {
        error_log('getUserStatistics error: ' . $e->getMessage());
        json_out(500, ['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Helper: Count other active admins (excluding the target admin)
 */
function countOtherActiveAdmins(PDO $pdo, int $excludeUserId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND user_id != ?");
    $stmt->execute([$excludeUserId]);
    return (int)$stmt->fetchColumn();
}

/**
 * ✅ Updated: Generic notifications + email change pending verification (no DB schema changes)
 */
function updateUser(array $data): void
{
    $pdo = db();

    if (!isset($data['user_id'])) {
        json_out(400, ['success' => false, 'message' => 'User ID is required']);
        return;
    }

    $actorAdminId = (int)($_SESSION['user_id'] ?? 0);
    $userId = (int)$data['user_id'];

    try {
        $stmt = $pdo->prepare("SELECT user_id, user_email, user_fullname, username, role, status, user_phone FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentUser) {
            json_out(404, ['success' => false, 'message' => 'User not found']);
            return;
        }

        $targetRole = (string)$currentUser['role'];
        $currentStatus = (string)$currentUser['status'];

        if ($actorAdminId > 0 && $actorAdminId === $userId && isset($data['status'])) {
            json_out(403, ['success' => false, 'message' => 'You cannot change your own account status.']);
            return;
        }

        $newStatus = null;
        if (isset($data['status'])) {
            $candidate = (string)$data['status'];
            if (!in_array($candidate, ['active', 'inactive', 'suspended'], true)) {
                json_out(400, ['success' => false, 'message' => 'Invalid status value']);
                return;
            }
            $newStatus = $candidate;
        }

        if ($newStatus !== null && $targetRole === 'admin') {
            if ($newStatus !== 'active' && $currentStatus === 'active') {
                $otherActiveAdmins = countOtherActiveAdmins($pdo, $userId);
                if ($otherActiveAdmins <= 0) {
                    json_out(403, ['success' => false, 'message' => 'Cannot suspend or deactivate the last active admin.']);
                    return;
                }
            }
        }

        $updateFields = [];
        $params = [];

        $oldEmail = (string)$currentUser['user_email'];
        $oldFullName = (string)$currentUser['user_fullname'];
        $username = (string)$currentUser['username'];

        $nameChanged  = false;
        $phoneChanged = false;
        $statusChanged = false;

        // Email change -> pending verification flow
        $emailChangeRequested = false;
        $pendingNewEmail = $oldEmail;

        // FULL NAME
        if (isset($data['user_fullname']) && $data['user_fullname'] !== $currentUser['user_fullname']) {
            $updateFields[] = "user_fullname = ?";
            $params[] = (string)$data['user_fullname'];
            $nameChanged = true;
        }

        // EMAIL (DO NOT UPDATE IN DB NOW)
        if (isset($data['user_email']) && (string)$data['user_email'] !== $oldEmail) {
            $candidateEmail = (string)$data['user_email'];

            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_email = ? AND user_id != ?");
            $stmt->execute([$candidateEmail, $userId]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                json_out(400, ['success' => false, 'message' => 'Email already in use']);
                return;
            }

            $emailChangeRequested = true;
            $pendingNewEmail = $candidateEmail;

            // Block login until verified (but do NOT overwrite to suspended)
            $updateFields[] = "email_verified_at = NULL";
            if ($currentStatus !== 'suspended') {
                $updateFields[] = "status = 'inactive'";
                $statusChanged = ($currentStatus !== 'inactive'); // treat as status change for audit only
                // But notification subject for status should only be for explicit admin status change.
                // So we will NOT send "Account SUSPENDED/ACTIVATED" for this auto-inactive.
            }
        }

        // PHONE
        if (isset($data['user_phone']) && $data['user_phone'] !== ($currentUser['user_phone'] ?? null)) {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_phone = ? AND user_id != ?");
            $stmt->execute([(string)$data['user_phone'], $userId]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                json_out(400, ['success' => false, 'message' => 'Phone number already in use']);
                return;
            }

            $updateFields[] = "user_phone = ?";
            $params[] = (string)$data['user_phone'];
            $phoneChanged = true;
        }

        // STATUS (explicit admin action)
        $explicitStatusChange = false;
        if ($newStatus !== null && $newStatus !== $currentStatus) {
            $updateFields[] = "status = ?";
            $params[] = $newStatus;
            $statusChanged = true;
            $explicitStatusChange = true;
        }

        if (empty($updateFields)) {
            json_out(400, ['success' => false, 'message' => 'No fields to update']);
            return;
        }

        $params[] = $userId;

        $pdo->beginTransaction();

        $sql = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Audit logging (keep detailed logging internally; emails remain generic)
        $eventType = 'admin_user_updated';
        $severity = 'info';

        if ($explicitStatusChange) {
            if ($newStatus === 'suspended') {
                $eventType = 'admin_user_suspended';
                $severity = 'warning';
            } elseif ($newStatus === 'inactive') {
                $eventType = 'admin_user_deactivated';
                $severity = 'warning';
            } elseif ($newStatus === 'active') {
                $eventType = 'admin_user_activated';
            }
        }

        logAdminAction(
            $pdo,
            $eventType,
            "Admin updated user '{$username}' (ID: {$userId})",
            [
                'target_user_id' => $userId,
                'target_username' => $username,
                'admin_id' => $actorAdminId,
                'email_change_requested' => $emailChangeRequested,
                'timestamp' => date('Y-m-d H:i:s')
            ],
            $severity
        );

        $pdo->commit();



      
        if ($emailChangeRequested) {
            // Verification link to NEW email
            $token = make_email_change_token($userId, $pendingNewEmail, 86400); // 24 hours
            $link = build_confirm_email_change_link($token);
            sendEmailChangeVerificationLink($pendingNewEmail, $oldFullName, $link);
            // Also send other generic notifications if name/phone changed too (to old email)
            if ($nameChanged) {
                sendProfileInfoUpdated($oldEmail, $oldFullName);
            }
            if ($phoneChanged) {
                sendContactInfoUpdated($oldEmail, $oldFullName);
            }

            // Only send Account SUSPENDED/ACTIVATED if admin explicitly changed status
            if ($explicitStatusChange) {
                sendAccountStatusNotification($oldEmail, $oldFullName, $newStatus ?? $currentStatus);
            }
        } else {
            // No email change: send generic notices to current email
            if ($nameChanged) {
                sendProfileInfoUpdated($oldEmail, $oldFullName);
            }
            if ($phoneChanged) {
                sendContactInfoUpdated($oldEmail, $oldFullName);
            }
            if ($explicitStatusChange) {
                sendAccountStatusNotification($oldEmail, $oldFullName, $newStatus ?? $currentStatus);
            }
        }

        json_out(200, [
            'success' => true,
            'message' => $emailChangeRequested
                ? 'User updated. Email change pending verification.'
                : 'User updated successfully'
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Update user error: ' . $e->getMessage());
        json_out(500, ['success' => false, 'message' => 'Failed to update user']);
    }
}

function deleteUser(int $userId): void
{
    $pdo = db();

    $actorAdminId = (int)($_SESSION['user_id'] ?? 0);

    try {
        $stmt = $pdo->prepare("SELECT user_id, username, role, status, user_email, user_fullname FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            json_out(404, ['success' => false, 'message' => 'User not found']);
            return;
        }

        if ($user['role'] === 'admin') {
            json_out(403, ['success' => false, 'message' => 'Cannot delete admin users']);
            return;
        }

        $pdo->beginTransaction();

        logAdminAction(
            $pdo,
            'admin_user_deleted',
            "Admin permanently deleted user '{$user['username']}' (ID: {$userId})",
            [
                'deleted_user_id' => $userId,
                'deleted_username' => $user['username'],
                'admin_id' => $actorAdminId,
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'warning'
        );

        // (Kept as-is)
        sendAccountDeletionNotification((string)$user['user_email'], (string)$user['user_fullname']);

        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);

        $pdo->commit();

        json_out(200, [
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Delete user error: ' . $e->getMessage());
        json_out(500, ['success' => false, 'message' => 'Failed to delete user']);
    }
}

/* =========================
   EMAIL FUNCTIONS (updated)
   - Generic only
   - No old values
   - No phone numbers
   - No exact field names
   ========================= */

function sendLoginEmailUpdatedAlert(string $oldEmail, string $fullName): void
{
    try {
        $mail = require __DIR__ . '/mailer.php';
        $mail->setFrom('noreply@yourdomain.com', 'Admin System');
        $mail->addAddress($oldEmail);

        $mail->Subject = 'Login email updated';

        $mail->Body = "
        <html><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
          <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <p>Hello {$fullName},</p>
            <p>Your login email was updated.</p>
            <p>If you did not expect this change, please contact support immediately.</p>
            <p style='font-size: 12px; color: #6b7280;'>This is an automated message. Please do not reply.</p>
          </div>
        </body></html>";

        $mail->AltBody = "Hello {$fullName},\n\nYour login email was updated.\n\nIf you did not expect this change, please contact support immediately.";

        $mail->send();
    } catch (Exception $e) {
        error_log('Email alert failed: ' . $e->getMessage());
    }
}

function sendEmailChangeVerificationLink(string $newEmail, string $fullName, string $link): void
{
    try {
        $mail = require __DIR__ . '/mailer.php';
        $mail->setFrom('noreply@yourdomain.com', 'Admin System');
        $mail->addAddress($newEmail);

        $mail->Subject = 'Login email updated';

        $mail->Body = "
        <html><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
          <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <p>Hello {$fullName},</p>
            <p>Your login email was updated. Please confirm to complete this change.</p>
            <p><a href='{$link}' style='display:inline-block;background:#0891b2;color:#fff;padding:10px 14px;border-radius:8px;text-decoration:none;'>Confirm change</a></p>
            <p>This link will expire in 24 hours.</p>
            <p style='font-size: 12px; color: #6b7280;'>This is an automated message. Please do not reply.</p>
          </div>
        </body></html>";

        $mail->AltBody = "Hello {$fullName},\n\nYour login email was updated. Confirm to complete this change:\n{$link}\n\nThis link expires in 24 hours.";

        $mail->send();
    } catch (Exception $e) {
        error_log('Verification link email failed: ' . $e->getMessage());
    }
}

function sendProfileInfoUpdated(string $email, string $fullName): void
{
    try {
        $mail = require __DIR__ . '/mailer.php';
        $mail->setFrom('noreply@yourdomain.com', 'Admin System');
        $mail->addAddress($email);

        $mail->Subject = 'Your full name is updated';
        $mail->Body = "
        <html><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
          <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <p>Hello {$fullName},</p>
            <p>Your profile information was updated.</p>
            <p>If you did not expect this change, please contact support.</p>
            <p style='font-size: 12px; color: #6b7280;'>This is an automated message. Please do not reply.</p>
          </div>
        </body></html>";
        $mail->AltBody = "Hello {$fullName},\n\nYour profile information was updated.\n\nIf you did not expect this change, please contact support.";
        $mail->send();
    } catch (Exception $e) {
        error_log('Profile update email failed: ' . $e->getMessage());
    }
}

function sendContactInfoUpdated(string $email, string $fullName): void
{
    try {
        $mail = require __DIR__ . '/mailer.php';
        $mail->setFrom('noreply@yourdomain.com', 'Admin System');
        $mail->addAddress($email);

        $mail->Subject = 'Contact information updated';
        $mail->Body = "
        <html><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
          <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <p>Hello {$fullName},</p>
            <p>Your contact information was updated.</p>
            <p>If you did not expect this change, please contact support.</p>
            <p style='font-size: 12px; color: #6b7280;'>This is an automated message. Please do not reply.</p>
          </div>
        </body></html>";
        $mail->AltBody = "Hello {$fullName},\n\nYour contact information was updated.\n\nIf you did not expect this change, please contact support.";
        $mail->send();
    } catch (Exception $e) {
        error_log('Contact update email failed: ' . $e->getMessage());
    }
}

function sendAccountStatusNotification(string $email, string $fullName, string $status): void
{
    $status = strtolower(trim($status));

    // ✅ Subject depends on the new status
    if ($status === 'active') {
        $subject = 'Account ACTIVATED';
        $message = 'Your account status has been changed to ACTIVE. You can now access the system normally.';
    } elseif ($status === 'inactive') {
        $subject = 'Account INACTIVATED';
        $message = 'Your account status has been changed to INACTIVE. Your access may be limited until reactivated.';
    } elseif ($status === 'suspended') {
        $subject = 'Account SUSPENDED';
        $message = 'Your account has been SUSPENDED. Please contact the admin for assistance.';
    } else {
        // Fallback (should not happen)
        $subject = 'Account Status Updated';
        $message = 'Your account status has been updated.';
    }

    try {
        $mail = require __DIR__ . '/mailer.php';
        $mail->setFrom('noreply@yourdomain.com', 'Admin System');
        $mail->addAddress($email);

        $mail->Subject = $subject;

        $mail->Body = "
        <html><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
          <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <p>Hello {$fullName},</p>
            <p>{$message}</p>
            <p style='font-size: 12px; color: #6b7280;'>This is an automated message. Please do not reply.</p>
          </div>
        </body></html>";

        $mail->AltBody = "Hello {$fullName},\n\n{$message}\n\nThis is an automated message. Please do not reply.";

        $mail->send();
    } catch (Exception $e) {
        error_log('Status email failed: ' . $e->getMessage());
    }
}


/* ===== Existing deletion email (kept) ===== */
function sendAccountDeletionNotification(string $email, string $fullName): void
{
    try {
        $mail = require __DIR__ . '/mailer.php';

        $mail->setFrom('noreply@yourdomain.com', 'Admin System');
        $mail->addAddress($email);

        $mail->Subject = 'Account Deletion Notice';

        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #ef4444;'>Account Deletion Notice</h2>
                <p>Hello {$fullName},</p>
                <p>Your account has been deleted by an administrator.</p>
                <p>All your data and access have been removed from the system.</p>
                <p>If you believe this was done in error, please contact your administrator immediately.</p>
                <hr style='border: 1px solid #e5e7eb; margin: 20px 0;'>
                <p style='font-size: 12px; color: #6b7280;'>This is an automated message. Please do not reply.</p>
            </div>
        </body>
        </html>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log('Email notification failed: ' . $e->getMessage());
    }
}

function logAdminAction(
    PDO $pdo,
    string $eventType,
    string $description,
    array $metadata = [],
    string $severity = 'info'
): void {
    try {
        $sql = "
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
            (:user_id, :event_type, 'admin', :severity, :description, :ua, :metadata, NOW())
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => (int)($_SESSION['user_id'] ?? 0),
            ':event_type' => $eventType,
            ':severity' => $severity,
            ':description' => $description,
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE)
        ]);
    } catch (PDOException $e) {
        error_log('❌ Failed to log admin action: ' . $e->getMessage());
    }
}
