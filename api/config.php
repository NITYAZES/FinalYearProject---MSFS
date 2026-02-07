<?php
declare(strict_types=1);



function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  // Local quick-start: root with empty password
  $dsn    = 'mysql:host=localhost;dbname=multimediasecurefilesharing;charset=utf8mb4';
  $dbUser = 'root';
  $dbPass = ''; // <- no password

  $opts = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ];

  try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $opts);
    return $pdo;
  } catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    // Don't expose database details to client
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
  }
}

function json_out(int $code, array $payload): never {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}


const EMAIL_CHANGE_HMAC_KEY = 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET';


const EMAIL_CHANGE_TTL_SECONDS = 86400;


function b64url_encode(string $bin): string {
  return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function b64url_decode(string $s): string {
  $s = strtr($s, '-_', '+/');
  $pad = strlen($s) % 4;
  if ($pad !== 0) $s .= str_repeat('=', 4 - $pad);
  $out = base64_decode($s, true);
  return $out === false ? '' : $out;
}


function make_email_change_token(int $userId, string $newEmail, int $ttlSeconds = EMAIL_CHANGE_TTL_SECONDS): string {
  $payload = [
    'uid' => $userId,
    'em'  => $newEmail,
    'exp' => time() + $ttlSeconds,
    'n'   => bin2hex(random_bytes(12)),
  ];

  $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
  if ($json === false) {
    $json = '{}';
  }

  $payloadB64 = b64url_encode($json);
  $sigBin = hash_hmac('sha256', $payloadB64, EMAIL_CHANGE_HMAC_KEY, true);
  $sigB64 = b64url_encode($sigBin);

  return $payloadB64 . '.' . $sigB64;
}


function verify_email_change_token(string $token): array {
  if ($token === '' || !str_contains($token, '.')) {
    throw new Exception('Invalid token format');
  }

  [$payloadB64, $sigB64] = explode('.', $token, 2);

  $expectedSigBin = hash_hmac('sha256', $payloadB64, EMAIL_CHANGE_HMAC_KEY, true);
  $providedSigBin = b64url_decode($sigB64);

  if ($providedSigBin === '' || !hash_equals($expectedSigBin, $providedSigBin)) {
    throw new Exception('Invalid token signature');
  }

  $payloadJson = b64url_decode($payloadB64);
  $payload = json_decode($payloadJson, true);

  if (!is_array($payload)) {
    throw new Exception('Invalid token payload');
  }

  if (!isset($payload['uid'], $payload['em'], $payload['exp'])) {
    throw new Exception('Incomplete token data');
  }

  $exp = (int)$payload['exp'];
  if ($exp < time()) {
    throw new Exception('Token expired');
  }

  $uid = (int)$payload['uid'];
  $email = (string)$payload['em'];

  if ($uid <= 0 || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception('Invalid token data');
  }

  return [
    'user_id'   => $uid,
    'new_email' => $email,
    'expires'   => $exp,
    'nonce'     => (string)($payload['n'] ?? ''),
  ];
}


function build_confirm_email_change_link(string $token): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'); 

  return "{$scheme}://{$host}{$base}/confirm_email_change.php?token=" . rawurlencode($token);
}
