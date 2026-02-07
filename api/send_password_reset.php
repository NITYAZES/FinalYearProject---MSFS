<?php
// api/send_password_reset.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';

// Auto-detect protocol or use HTTPS by default
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . '://' . $host . '/FinalYearProject');

/** Consistent JSON response helper */
$respond = function (int $code, array $payload) {
  if (function_exists('json_out')) {
    json_out($code, $payload);
  }
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
};

// CSRF validation for POST requests
csrf_require_or_403($respond);

/* -------------------- audit helpers -------------------- */
function get_client_ip(): string {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  return is_string($ip) ? $ip : '';
}

function get_user_agent(): string {
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  return is_string($ua) ? substr($ua, 0, 512) : '';
}

function audit_log(
  PDO $pdo,
  ?int $userId,
  string $eventType,
  string $category,
  string $severity,
  string $description,
  array $metadata = []
): void {
  try {
    $metadata = array_merge(['ip' => get_client_ip()], $metadata);

    $metaJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($metaJson === false) $metaJson = '{}';

    $stmt = $pdo->prepare(
      'INSERT INTO security_audit_log
        (user_id, event_type, event_category, severity, description, user_agent, metadata_json, created_at)
       VALUES
        (:uid, :etype, :cat, :sev, :descr, :ua, :meta, NOW())'
    );

    $stmt->execute([
      ':uid'   => $userId,
      ':etype' => $eventType,
      ':cat'   => $category,
      ':sev'   => $severity,
      ':descr' => $description,
      ':ua'    => get_user_agent(),
      ':meta'  => $metaJson,
    ]);
  } catch (Throwable $e) {
    // Never break password reset flow
    error_log('security_audit_log insert failed: ' . $e->getMessage());
  }
}

try {
  // Read form data (POST)
  $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';

  // Basic validation
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $respond(422, ['error' => 'Valid email is required']);
  }

  $pdo = db();

  // Find user by email (users.user_email)
  $find = $pdo->prepare('SELECT user_id FROM users WHERE user_email = ? LIMIT 1');
  $find->execute([$email]);
  $user = $find->fetch(PDO::FETCH_ASSOC);

  // Always generate a token (don't leak)
  $token      = bin2hex(random_bytes(16));
  $token_hash = hash('sha256', $token, true);
  $expiry     = (new DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');

  $emailParts  = explode('@', $email);
  $emailDomain = $emailParts[1] ?? '';

  if ($user) {
    $userId = (int)$user['user_id'];

    // AUDIT: reset requested (known user)
    audit_log(
      $pdo,
      $userId,
      'PASSWORD_RESET_REQUESTED',
      'AUTH',
      'INFO',
      'Password reset requested',
      [
        'email_domain' => $emailDomain,
        'expires_at'   => $expiry
      ]
    );

    // Invalidate previous tokens for this user
    $del = $pdo->prepare('DELETE FROM password_reset_requests WHERE user_id = ?');
    $del->execute([$userId]);

    // Insert new reset request
    $ins = $pdo->prepare(
      'INSERT INTO password_reset_requests (user_id, reset_token_hash, reset_token_expires_at, created_at)
       VALUES (?, ?, ?, NOW())'
    );
    $ins->bindValue(1, $userId, PDO::PARAM_INT);
    $ins->bindValue(2, $token_hash, PDO::PARAM_STR);
    $ins->bindValue(3, $expiry, PDO::PARAM_STR);
    $ins->execute();

    // Send email (best-effort)
    $resetUrl = BASE_URL . '/api/reset_password_2step.php?token=' . urlencode($token);

    /** @var PHPMailer\PHPMailer\PHPMailer $mail */
    $mail = require __DIR__ . '/mailer.php';
    $mail->setFrom('noreply@example.com', 'Multimedia Secure File Share');
    $mail->addAddress($email);
    $mail->Subject = 'Password Reset';
    $mail->isHTML(true);
    $mail->Body = <<<HTML
<p>We received a request to reset your password. If you made this request, click the link below:</p>
<p><a href="{$resetUrl}">Reset your password</a></p>
<p>This link expires in 30 minutes. If you didn't request a reset, you can ignore this email.</p>
HTML;

    try {
      $mail->send();
    } catch (Throwable $e) {
      error_log('Mailer error: ' . $e->getMessage());
      // Optional audit: mail failure
      audit_log(
        $pdo,
        $userId,
        'PASSWORD_RESET_EMAIL_SEND_FAILED',
        'SERVER',
        'MEDIUM',
        'Password reset email failed to send',
        ['email_domain' => $emailDomain]
      );
    }
  } else {
    // AUDIT: possible enumeration (unknown email)
    audit_log(
      $pdo,
      null,
      'POSSIBLE_ENUMERATION',
      'SECURITY',
      'MEDIUM',
      'Password reset requested for non-existent email',
      [
        'email_domain' => $emailDomain
      ]
    );
  }

  // Redirect to success page regardless (avoid enumeration)
  $emailDomain = $emailDomain !== '' ? $emailDomain : 'your email';
  header('Location: ' . BASE_URL . '/reset_success.html?domain=' . urlencode($emailDomain));
  exit;

} catch (Throwable $e) {
  error_log('Password reset error: ' . $e->getMessage());
  $respond(500, ['error' => 'Internal server error']);
}