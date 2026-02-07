<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/activity_logger.php';
require_once __DIR__ . '/admin_notification_helper.php'; 
require_once __DIR__ . '/user_notification_helper.php';  

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');

session_start();


header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

function bad(int $code, string $msg, ?string $details = null): never
{
    if ($details) {
        error_log("Upload Error [{$code}]: {$msg} | Details: {$details}");
    }
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Must be logged in for ALL upload actions
 */
if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    bad(401, 'Unauthorized. Please log in.');
}

function computeCipherEntropy(string $path, int $maxBytes = 250000): array
{
    if (!is_readable($path)) {
        return ['entropyBitsPerByte' => null, 'sampleSize' => 0];
    }

    $fh = @fopen($path, 'rb');
    if ($fh === false) {
        return ['entropyBitsPerByte' => null, 'sampleSize' => 0];
    }

    $maxBytes = max(1024, $maxBytes);
    $freq = array_fill(0, 256, 0);
    $bytesRead = 0;

    while (!feof($fh) && $bytesRead < $maxBytes) {
        $left = $maxBytes - $bytesRead;
        $chunk = fread($fh, min(8192, $left));
        if ($chunk === '' || $chunk === false) break;

        $len = strlen($chunk);
        $bytesRead += $len;

        for ($i = 0; $i < $len; $i++) {
            $freq[ord($chunk[$i])] += 1;
        }
    }

    fclose($fh);

    if ($bytesRead === 0) {
        return ['entropyBitsPerByte' => null, 'sampleSize' => 0];
    }

    $entropy = 0.0;
    foreach ($freq as $count) {
        if ($count === 0) continue;
        $p = $count / $bytesRead;
        $entropy -= $p * log($p, 2);
    }

    return ['entropyBitsPerByte' => $entropy, 'sampleSize' => $bytesRead];
}

function calculateEncryptionScore(array $metrics): array
{
    $score = 0;
    $maxScore = 100;
    $breakdown = [];
    $recs = [];

    if (($metrics['rsaKeySize'] ?? 0) >= 2048) {
        $score += 15;
        $breakdown['rsaKey'] = ['score' => 15, 'max' => 15, 'status' => 'excellent'];
    } else {
        $recs[] = 'Use at least 2048-bit RSA keys.';
    }

    if (($metrics['aesKeySize'] ?? 0) >= 256) {
        $score += 15;
        $breakdown['aesKey'] = ['score' => 15, 'max' => 15, 'status' => 'excellent'];
    } else {
        $recs[] = 'Use a 256-bit AES key.';
    }

    if (($metrics['encryptionAlgorithm'] ?? '') === 'AES-GCM') {
        $score += 10;
        $breakdown['algorithm'] = ['score' => 10, 'max' => 10, 'status' => 'excellent'];
    }

    if (($metrics['keyExchange'] ?? '') === 'RSA-OAEP') {
        $score += 10;
        $breakdown['keyExchange'] = ['score' => 10, 'max' => 10, 'status' => 'excellent'];
    }

    if (($metrics['hashAlgorithm'] ?? '') === 'SHA-256') {
        $score += 10;
        $breakdown['hash'] = ['score' => 10, 'max' => 10, 'status' => 'excellent'];
    }

    if (!empty($metrics['authenticatedEncryption'])) {
        $score += 5;
        $breakdown['authEncryption'] = ['score' => 5, 'max' => 5, 'status' => 'excellent'];
    }

    if (($metrics['ivLength'] ?? 0) >= 12) {
        $score += 10;
        $breakdown['ivQuality'] = ['score' => 10, 'max' => 10, 'status' => 'excellent'];
    }

    if (!empty($metrics['expiryEnabled'])) {
        $score += 8;
        $breakdown['expiry'] = ['score' => 8, 'max' => 8, 'status' => 'enabled'];
    }

    if (!empty($metrics['downloadLimitEnabled'])) {
        $score += 7;
        $breakdown['downloadLimit'] = ['score' => 7, 'max' => 7, 'status' => 'enabled'];
    }

    if (!empty($metrics['e2eeEnabled'])) {
        $score += 5;
        $breakdown['e2ee'] = ['score' => 5, 'max' => 5, 'status' => 'enabled'];
    }

    $entropyBits = $metrics['cipherEntropyBitsPerByte'] ?? null;
    $entropySample = $metrics['cipherSampleSize'] ?? null;

    if ($entropyBits !== null && $entropyBits > 0 && $entropySample !== null && $entropySample >= 1024) {
        if ($entropyBits >= 7.95) {
            $entropyScore = 15;
            $status = 'excellent';
        } elseif ($entropyBits >= 7.85) {
            $entropyScore = 13;
            $status = 'very-good';
        } elseif ($entropyBits >= 7.7) {
            $entropyScore = 11;
            $status = 'good';
        } elseif ($entropyBits >= 7.5) {
            $entropyScore = 8;
            $status = 'fair';
        } else {
            $entropyScore = 5;
            $status = 'weak';
        }

        $breakdown['cipherEntropy'] = [
            'score' => $entropyScore,
            'max' => 15,
            'status' => $status,
            'entropyBitsPerByte' => round((float)$entropyBits, 3),
        ];

        $score += $entropyScore;
    }

    $score = min($score, $maxScore);
    $percentage = round(($score / $maxScore) * 100, 1);

    $rating = 'Weak';
    $ratingColor = '#dc2626';

    if ($percentage >= 95) {
        $rating = 'Excellent';
        $ratingColor = '#16a34a';
    } elseif ($percentage >= 85) {
        $rating = 'Very Good';
        $ratingColor = '#22c55e';
    } elseif ($percentage >= 75) {
        $rating = 'Good';
        $ratingColor = '#4ade80';
    } elseif ($percentage >= 60) {
        $rating = 'Fair';
        $ratingColor = '#facc15';
    }

    return [
        'score' => $score,
        'maxScore' => $maxScore,
        'percentage' => $percentage,
        'rating' => $rating,
        'ratingColor' => $ratingColor,
        'breakdown' => $breakdown,
        'recommendations' => $recs,
        'cipherEntropyBitsPerByte' => $entropyBits,
        'cipherSampleSize' => $entropySample,
    ];
}

function formatBytes(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}

/**
 * GET users endpoint
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'users') {
    try {
        $currentUserId = (int)$_SESSION['user_id'];
        $pdo = db();

        $stmt = $pdo->prepare('
            SELECT DISTINCT u.user_id, u.user_fullname, u.username, u.user_email
            FROM users u
            INNER JOIN user_crypto_keys ck ON u.user_id = ck.user_id
            WHERE u.status = "active"
              AND u.email_verified_at IS NOT NULL
              AND ck.key_status = "active"
              AND ck.public_key_jwk IS NOT NULL
              AND u.user_id <> :current
            ORDER BY u.username ASC
        ');
        $stmt->execute([':current' => $currentUserId]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'success' => true, 'users' => $users], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        error_log('List users error: ' . $e->getMessage());
        bad(500, 'Failed to load users.');
    }
}

/**
 * POST upload endpoint
 */
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        bad(405, 'Method not allowed');
    }

    $senderId = (int)$_SESSION['user_id'];

    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        bad(400, 'No file uploaded or upload error occurred');
    }

    $file = $_FILES['file'];
    $maxSize = 100 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        bad(413, 'File too large. Maximum size is 100MB.');
    }

    $metadataJson = $_POST['metadata'] ?? '';
    if ($metadataJson === '') {
        bad(400, 'Missing metadata');
    }

    try {
        $metadata = json_decode($metadataJson, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        bad(400, 'Invalid metadata JSON');
    }

    $receiverEmail = $metadata['receiverEmail'] ?? '';
    $receiverEmail = is_string($receiverEmail) ? trim($receiverEmail) : '';
    if ($receiverEmail === '') {
        bad(400, 'No recipient specified');
    }

    $receiverEmailValidated = filter_var($receiverEmail, FILTER_VALIDATE_EMAIL);
    if (!$receiverEmailValidated) {
        bad(400, 'Invalid receiver email address');
    }
    $receiverEmail = (string)$receiverEmailValidated;

    $requiredFields = ['encFileKey', 'hashEnc', 'policy', 'fileName', 'fileSize', 'mimeType'];
    foreach ($requiredFields as $field) {
        if (!isset($metadata[$field]) || $metadata[$field] === '' || $metadata[$field] === null) {
            bad(400, "Missing required field: {$field}");
        }
    }

    $policy = $metadata['policy'];
    if (!is_array($policy) || !isset($policy['expiryTime'], $policy['maxDecryptCount'])) {
        bad(400, 'Invalid policy structure');
    }

    // ✅ MANDATORY: Expiry Time Validation
    if (!isset($policy['expiryTime']) || $policy['expiryTime'] === '' || $policy['expiryTime'] === null) {
        bad(400, 'Expiry time is required for security.');
    }
    
    $expiryTime = $policy['expiryTime'];
    if (!is_numeric($expiryTime)) {
        bad(400, 'Expiry time must be a valid timestamp.');
    }
    
    $expiryTime = (int)$expiryTime;
    $currentTime = time();
    
    if ($expiryTime <= $currentTime) {
        bad(400, 'Expiry time must be in the future.');
    }
    
    // Optional: Enforce maximum expiry time (e.g., 90 days)
    $maxExpirySeconds = 90 * 24 * 60 * 60; // 90 days
    if ($expiryTime > ($currentTime + $maxExpirySeconds)) {
        bad(400, 'Expiry time cannot exceed 90 days from now.');
    }

    // ✅ MANDATORY: Download Limit Validation
    if (!isset($policy['maxDecryptCount']) || $policy['maxDecryptCount'] === '' || $policy['maxDecryptCount'] === null) {
        bad(400, 'Download limit is required for security.');
    }
    
    if (!is_numeric($policy['maxDecryptCount'])) {
        bad(400, 'Download limit must be a valid number.');
    }
    
    $maxDecryptCount = (int)$policy['maxDecryptCount'];
    
    if ($maxDecryptCount < 1) {
        bad(400, 'Download limit must be at least 1.');
    }
    
    if ($maxDecryptCount > 1000) {
        bad(400, 'Download limit cannot exceed 1000.');
    }

    $pdo = db();

    // Sender info
    $senderStmt = $pdo->prepare('SELECT user_fullname, username, user_email FROM users WHERE user_id = :id LIMIT 1');
    $senderStmt->execute([':id' => $senderId]);
    $sender = $senderStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Receiver info
    $stmt = $pdo->prepare('
        SELECT u.user_id, u.user_fullname, u.username, u.user_email, u.email_verified_at,
               ck.public_key_jwk
        FROM users u
        LEFT JOIN user_crypto_keys ck ON u.user_id = ck.user_id
            AND ck.key_status = "active"
        WHERE u.user_email = :email AND u.status = "active"
        LIMIT 1
    ');
    $stmt->execute([':email' => $receiverEmail]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receiver) {
        bad(404, 'Receiver not found or account inactive');
    }
    if (empty($receiver['email_verified_at'])) {
        bad(403, 'Receiver email not verified. Cannot send file.');
    }
    if (empty($receiver['public_key_jwk'])) {
        bad(400, 'Receiver has not set up encryption keys');
    }

    $receiverId = (int)$receiver['user_id'];
    if ($senderId === $receiverId) {
        bad(400, 'Cannot send file to yourself');
    }

    // Metrics
    $originalSize = (int)$metadata['fileSize'];
    $encryptedSize = (int)($file['size'] ?? 0);
    $sizeOverhead = $encryptedSize - $originalSize;
    $sizeOverheadPercent = $originalSize > 0 ? round(($sizeOverhead / $originalSize) * 100, 2) : 0.0;

    $entropyInfo = computeCipherEntropy($file['tmp_name']);

    $encryptionMetrics = [
        'rsaKeySize' => 2048,
        'aesKeySize' => 256,
        'encryptionAlgorithm' => 'AES-GCM',
        'keyExchange' => 'RSA-OAEP',
        'hashAlgorithm' => 'SHA-256',
        'authenticatedEncryption' => true,
        'ivLength' => 12,
        'expiryEnabled' => true,
        'downloadLimitEnabled' => $maxDecryptCount > 0 && $maxDecryptCount < 1000,
        'e2eeEnabled' => true,
        'originalSize' => $originalSize,
        'encryptedSize' => $encryptedSize,
        'encryptionTime' => $metadata['encryptionTime'] ?? null,
        'cipherEntropyBitsPerByte' => $entropyInfo['entropyBitsPerByte'],
        'cipherSampleSize' => $entropyInfo['sampleSize'],
    ];

    $encryptionScore = calculateEncryptionScore($encryptionMetrics);

    // Store encrypted blob
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
        bad(500, 'Failed to initialize upload storage.');
    }

    $fileId = bin2hex(random_bytes(16));
    $storagePath = $uploadDir . $fileId . '.enc';

    if (!move_uploaded_file($file['tmp_name'], $storagePath)) {
        bad(500, 'Failed to store file');
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // 1. Insert into shared_files
        $stmt = $pdo->prepare('
            INSERT INTO shared_files (
                file_id, sender_id, receiver_id, file_name, file_size, mime_type,
                storage_path, enc_file_key, hash_enc, policy_json,
                expiry_time, max_decrypt_count, decrypt_count,
                uploaded_at
            ) VALUES (
                :file_id, :sender_id, :receiver_id, :file_name, :file_size, :mime_type,
                :storage_path, :enc_file_key, :hash_enc, :policy_json,
                FROM_UNIXTIME(:expiry_time), :max_decrypt_count, 0,
                NOW()
            )
        ');

        $result = $stmt->execute([
            ':file_id' => $fileId,
            ':sender_id' => $senderId,
            ':receiver_id' => $receiverId,
            ':file_name' => (string)$metadata['fileName'],
            ':file_size' => $originalSize,
            ':mime_type' => (string)$metadata['mimeType'],
            ':storage_path' => $storagePath,
            ':enc_file_key' => (string)$metadata['encFileKey'],
            ':hash_enc' => (string)$metadata['hashEnc'],
            ':policy_json' => json_encode($policy, JSON_UNESCAPED_UNICODE),
            ':expiry_time' => (int)$expiryTime,
            ':max_decrypt_count' => $maxDecryptCount,
        ]);

        if (!$result) {
            throw new Exception('Failed to insert into shared_files');
        }

        // 2. Insert into file_recipients
        $recipientStmt = $pdo->prepare('
            INSERT INTO file_recipients (
                file_id, user_id, permission_level, access_count, status, added_at
            ) VALUES (
                :file_id, :user_id, "download", 0, "active", NOW()
            )
        ');

        $result = $recipientStmt->execute([
            ':file_id' => $fileId,
            ':user_id' => $receiverId
        ]);

        if (!$result) {
            throw new Exception('Failed to insert into file_recipients');
        }

        // 3. Insert encryption metrics
        $metricsStmt = $pdo->prepare('
            INSERT INTO encryption_metrics (
                file_id, sender_id, receiver_id,
                encryption_score, encryption_rating, encryption_percentage,
                rsa_key_size, aes_key_size, iv_length,
                encryption_algorithm, key_exchange_algorithm, hash_algorithm,
                authenticated_encryption, e2ee_enabled, expiry_enabled, download_limit_enabled,
                encryption_time_ms, original_size, encrypted_size,
                size_overhead_bytes, size_overhead_percent,
                score_breakdown_json, recommendations_json,
                cipher_entropy_bits_per_byte, cipher_sample_size
            ) VALUES (
                :file_id, :sender_id, :receiver_id,
                :score, :rating, :percentage,
                :rsa_size, :aes_size, :iv_len,
                :enc_algo, :key_exchange, :hash_algo,
                :auth_enc, :e2ee, :expiry, :dl_limit,
                :enc_time, :orig_size, :enc_size,
                :overhead_bytes, :overhead_pct,
                :breakdown, :recommendations,
                :entropy_bits, :entropy_sample
            )
        ');

        $result = $metricsStmt->execute([
            ':file_id' => $fileId,
            ':sender_id' => $senderId,
            ':receiver_id' => $receiverId,
            ':score' => $encryptionScore['score'],
            ':rating' => $encryptionScore['rating'],
            ':percentage' => $encryptionScore['percentage'],
            ':rsa_size' => 2048,
            ':aes_size' => 256,
            ':iv_len' => 12,
            ':enc_algo' => 'AES-GCM',
            ':key_exchange' => 'RSA-OAEP',
            ':hash_algo' => 'SHA-256',
            ':auth_enc' => 1,
            ':e2ee' => 1,
            ':expiry' => 1,
            ':dl_limit' => ($maxDecryptCount > 0 && $maxDecryptCount < 1000) ? 1 : 0,
            ':enc_time' => isset($metadata['encryptionTime']) ? (int)$metadata['encryptionTime'] : null,
            ':orig_size' => $originalSize,
            ':enc_size' => $encryptedSize,
            ':overhead_bytes' => $sizeOverhead,
            ':overhead_pct' => $sizeOverheadPercent,
            ':breakdown' => json_encode($encryptionScore['breakdown'], JSON_UNESCAPED_UNICODE),
            ':recommendations' => json_encode($encryptionScore['recommendations'], JSON_UNESCAPED_UNICODE),
            ':entropy_bits' => $encryptionScore['cipherEntropyBitsPerByte'],
            ':entropy_sample' => $encryptionScore['cipherSampleSize'],
        ]);

        if (!$result) {
            throw new Exception('Failed to insert into encryption_metrics');
        }

        // 4. Log to security_audit_log
        $logStmt = $pdo->prepare('
            INSERT INTO security_audit_log
            (user_id, event_type, event_category, severity, description, user_agent, metadata_json)
            VALUES (:user_id, "file_uploaded", "file", "info",
                    :description, :ua, :metadata)
        ');

        $logMetadata = json_encode([
            'file_id' => $fileId,
            'file_name' => $metadata['fileName'],
            'file_size' => $originalSize,
            'receiver_id' => $receiverId,
            'receiver_email' => $receiverEmail
        ], JSON_UNESCAPED_UNICODE);

        $logStmt->execute([
            ':user_id' => $senderId,
            ':description' => "Uploaded encrypted file '{$metadata['fileName']}' to {$receiverEmail}",
            ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
            ':metadata' => $logMetadata
        ]);

        // 5. Log to file_access_log
        $fileLogStmt = $pdo->prepare('
            INSERT INTO file_access_log (file_id, user_id, action, metadata_json)
            VALUES (:file_id, :user_id, "viewed", :metadata)
        ');

        $fileLogStmt->execute([
            ':file_id' => $fileId,
            ':user_id' => $senderId,
            ':metadata' => json_encode(['action' => 'uploaded', 'receiver_id' => $receiverId], JSON_UNESCAPED_UNICODE)
        ]);

        // 6. Log activity (sender)
        logUserActivity(
            $pdo,
            $senderId,
            'file_uploaded',
            "Uploaded '{$metadata['fileName']}' (" . formatBytes($originalSize) . ") to {$receiver['user_fullname']}",
            null
        );

        // 7. Log activity (receiver)
        $senderNameForReceiver = $sender['user_fullname'] ?? $sender['username'] ?? 'A user';
        logUserActivity(
            $pdo,
            $receiverId,
            'file_received',
            "Received '{$metadata['fileName']}' (" . formatBytes($originalSize) . ") from {$senderNameForReceiver}",
            null
        );

        // 8. User notifications
        notifyFileUploaded(
            $pdo,
            $senderId,
            $receiverId,
            $fileId,
            (string)$metadata['fileName'],
            (int)$originalSize
        );

        $hoursUntilExpiry = ((int)$expiryTime - time()) / 3600;
        if ($hoursUntilExpiry <= 24) {
            notifyFileExpiringSoon(
                $pdo,
                $senderId,
                $receiverId,
                $fileId,
                (string)$metadata['fileName'],
                date('Y-m-d H:i:s', (int)$expiryTime)
            );
        }

        $pdo->commit();

        error_log("✅ File upload successful: {$fileId} from user {$senderId} to user {$receiverId}");

        // ✅ FIXED: call ADMIN function (array signature) instead of notifyFileUploaded()
        try {
            if (function_exists('notifyAdminFileUploaded')) {
                notifyAdminFileUploaded($pdo, [
                    'file_id'       => $fileId,
                    'file_name'     => (string)$metadata['fileName'],
                    'file_size'     => (int)$originalSize,
                    'sender_id'     => (int)$senderId,
                    'sender_name'   => (string)($sender['user_fullname'] ?? $sender['username'] ?? 'Unknown'),
                    'receiver_id'   => (int)$receiverId,
                    'receiver_name' => (string)($receiver['user_fullname'] ?? $receiver['username'] ?? 'Unknown'),
                ]);
            } else {
                error_log('notifyAdminFileUploaded() not found - check admin_notification_helper.php');
            }
        } catch (Throwable $notifErr) {
            error_log('Failed to send admin upload notification: ' . $notifErr->getMessage());
        }

    } catch (Throwable $e) {
        $pdo->rollBack();

        if (file_exists($storagePath)) {
            @unlink($storagePath);
        }

        error_log('❌ Upload transaction failed: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        bad(500, 'Failed to save file information. Please try again.', $e->getMessage());
    }

    // Email notification (outside transaction)
    $emailSent = false;
    $emailError = null;

    try {
        $senderName = $sender['user_fullname'] ?? $sender['username'] ?? 'A user';
        $receiverName = $receiver['user_fullname'] ?? $receiver['username'] ?? $receiverEmail;

        $baseUrl = $_ENV['APP_BASE_URL'] ?? 'http://localhost/FinalYearProject';
        $target = 'download.html?fileId=' . urlencode($fileId);
        $redirectUrl = rtrim($baseUrl, '/') . '/index.html?redirect=' . rawurlencode($target);

        $mail = require __DIR__ . '/mailer.php';

        $mail->setFrom('noreply@securefileshare.com', 'Secure File Share');
        $mail->addAddress($receiverEmail, $receiverName);
        $mail->Subject = '🔒 New Encrypted File from ' . $senderName;

        $fileSizeFormatted = formatBytes($originalSize);
        $pct = (string)$encryptionScore['percentage'];
        $rating = (string)$encryptionScore['rating'];
        $ratingColor = (string)$encryptionScore['ratingColor'];

        $safeFileName = htmlspecialchars((string)$metadata['fileName'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeReceiverName = htmlspecialchars((string)$receiverName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeSenderName = htmlspecialchars((string)$senderName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #1f2933 0%, #312e81 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .button { display: inline-block; background: linear-gradient(135deg, #1f2933 0%, #312e81 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                    .info-box { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid {$ratingColor}; }
                    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🔒 Secure File Received</h1>
                    </div>
                    <div class='content'>
                        <p>Hi <strong>{$safeReceiverName}</strong>,</p>
                        <p><strong>{$safeSenderName}</strong> has sent you a secure, encrypted file:</p>

                        <div class='info-box'>
                            <p><strong>📄 File:</strong> {$safeFileName}</p>
                            <p><strong>📦 Size:</strong> {$fileSizeFormatted}</p>
                            <p><strong>🔐 Encryption Score:</strong> <span style='color:{$ratingColor}'>{$pct}% ({$rating})</span></p>
                        </div>

                        <p><strong>Important:</strong> This file is encrypted end-to-end and requires your login credentials + 2FA to access.</p>

                        <center>
                            <a href='{$redirectUrl}' class='button'>🔓 Access Your File</a>
                        </center>


                        <div class='footer'>
                            <p>This is an automated message from Secure File Share.</p>
                            <p>If you did not expect this file, please ignore this email.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->AltBody =
            "New encrypted file from {$senderName}\n\n" .
            "File: " . (string)$metadata['fileName'] . " ({$fileSizeFormatted})\n" .
            "Encryption Score: {$pct}% ({$rating})\n\n" .
            "Access your file: {$redirectUrl}\n\n" .
            "This file is encrypted end-to-end and requires your login credentials + 2FA to access.";

        if ($mail->send()) {
            $emailSent = true;
        } else {
            $emailError = 'Email send failed';
        }

        $mail->smtpClose();
        unset($mail);
    } catch (Throwable $e) {
        $emailError = $e->getMessage();
        error_log("✗ Email error for {$receiverEmail}: " . $e->getMessage());
    }

    echo json_encode([
        'ok' => true,
        'message' => 'File uploaded and encrypted successfully',
        'fileId' => $fileId,
        'receiverName' => $receiver['user_fullname'] ?? $receiver['username'],
        'receiverEmail' => $receiverEmail,
        'emailSent' => $emailSent,
        'emailError' => $emailError,
        'encryptionMetrics' => $encryptionScore,
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    error_log('Upload error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    bad(500, 'Server error occurred. Please try again later.', $e->getMessage());
}