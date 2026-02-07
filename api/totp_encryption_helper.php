<?php
declare(strict_types=1);


/**
 * Get the TOTP master encryption key from environment
 * 
 * @return string Binary key (32 bytes for AES-256)
 * @throws Exception if key is not configured
 */
function getTotpMasterKey(): string {
    // Try environment variable first
    $keyBase64 = getenv('TOTP_MASTER_KEY');
    
    // Fallback to config file
    if (empty($keyBase64) && file_exists(__DIR__ . '/config_secure.php')) {
        require_once __DIR__ . '/config_secure.php';
        $keyBase64 = defined('TOTP_MASTER_KEY') ? TOTP_MASTER_KEY : null;
    }
    
    if (empty($keyBase64)) {
        throw new Exception('TOTP_MASTER_KEY not configured');
    }
    
    $key = base64_decode($keyBase64, true);
    
    if ($key === false || strlen($key) !== 32) {
        throw new Exception('Invalid TOTP_MASTER_KEY format (must be base64-encoded 32 bytes)');
    }
    
    return $key;
}


/**
 * Encrypt a TOTP secret
 * 
 * @param string $secret The plaintext TOTP secret (base32)
 * @return array ['ciphertext' => binary, 'iv' => binary (12 bytes), 'tag' => binary (16 bytes)]
 * @throws Exception on encryption failure
 */
function encryptTotpSecret(string $secret): array {
    if (empty($secret)) {
        throw new Exception('Cannot encrypt empty TOTP secret');
    }
    
    $key = getTotpMasterKey();
    
    // Generate random IV (12 bytes for GCM)
    $iv = random_bytes(12);
    
    // Encrypt using AES-256-GCM
    $tag = '';
    $ciphertext = openssl_encrypt(
        $secret,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        '', // no additional authenticated data
        16  // tag length
    );
    
    if ($ciphertext === false) {
        throw new Exception('TOTP secret encryption failed: ' . openssl_error_string());
    }
    
    return [
        'ciphertext' => $ciphertext,
        'iv' => $iv,
        'tag' => $tag
    ];
}

/**
 * Decrypt a TOTP secret
 * 
 * @param string $ciphertext Binary encrypted data
 * @param string $iv Binary IV (12 bytes)
 * @param string $tag Binary authentication tag (16 bytes)
 * @return string The plaintext TOTP secret
 * @throws Exception on decryption failure
 */
function decryptTotpSecret(string $ciphertext, string $iv, string $tag): string {
    if (empty($ciphertext) || empty($iv) || empty($tag)) {
        throw new Exception('Invalid encrypted TOTP secret data');
    }
    
    if (strlen($iv) !== 12) {
        throw new Exception('Invalid IV length for TOTP secret (expected 12 bytes)');
    }
    
    if (strlen($tag) !== 16) {
        throw new Exception('Invalid tag length for TOTP secret (expected 16 bytes)');
    }
    
    $key = getTotpMasterKey();
    
    // Decrypt using AES-256-GCM
    $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
    
    if ($plaintext === false) {
        throw new Exception('TOTP secret decryption failed - data may be corrupted or key is wrong');
    }
    
    return $plaintext;
}

/**
 * Migrate existing plaintext secret to encrypted format
 * Helper function for migration scripts
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param string $plaintextSecret Current plaintext secret
 * @return bool Success status
 */
function migrateTotpSecretToEncrypted(PDO $pdo, int $userId, string $plaintextSecret): bool {
    try {
        $encrypted = encryptTotpSecret($plaintextSecret);
        
        // Combine ciphertext and tag for storage
        $encryptedData = $encrypted['ciphertext'] . $encrypted['tag'];
        
        // Update user_mfa_totp table
        $stmt = $pdo->prepare('
            UPDATE user_mfa_totp 
            SET totp_secret_enc = :ciphertext,
                totp_secret_iv = :iv,
                totp_secret = NULL,
                updated_at = NOW()
            WHERE user_id = :user_id
        ');
        
        $result = $stmt->execute([
            'ciphertext' => $encryptedData,
            'iv' => $encrypted['iv'],
            'user_id' => $userId
        ]);
        
        // Also update users table if it has totp_secret
        try {
            $usersStmt = $pdo->prepare('
                UPDATE users 
                SET totp_secret = NULL
                WHERE user_id = :user_id
            ');
            $usersStmt->execute(['user_id' => $userId]);
        } catch (PDOException $e) {
            error_log('Optional: Failed to clear users.totp_secret: ' . $e->getMessage());
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log('Migration failed for user ' . $userId . ': ' . $e->getMessage());
        return false;
    }
}