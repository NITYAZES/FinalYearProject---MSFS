<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json; charset=utf-8');

function out(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}


function valid_username(string $u): bool {
  return (bool)preg_match('/^[A-Za-z0-9._-]{3,20}$/', $u);
}

try {
  $raw = file_get_contents('php://input');
  if ($raw === false || $raw === '') {
    out(400, ['ok' => false, 'message' => 'No input']);
  }

 
  if (strlen($raw) > 100_000) {
    out(413, ['ok' => false, 'message' => 'Payload too large']);
  }

  $input = json_decode($raw, true);
  if (!is_array($input)) {
    out(400, ['ok' => false, 'message' => 'Invalid JSON']);
  }

  $username = trim((string)($input['username'] ?? ''));

  if ($username === '') {
    out(400, ['ok' => false, 'message' => 'Username is required']);
  }

  if (!valid_username($username)) {
    out(422, [
      'ok' => false,
      'message' => 'Username must be 3–20 chars and only letters, numbers, dot, underscore, hyphen'
    ]);
  }

  $pdo = db();


  $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = :username LIMIT 1');
  $stmt->execute([':username' => $username]);

  $exists = (bool)$stmt->fetch();

  out(200, [
    'ok' => true,
    'available' => !$exists,
    'username' => $username
  ]);

} catch (PDOException $e) {
  error_log('Database error: ' . $e->getMessage());
  out(500, ['ok' => false, 'message' => 'Database error']);
} catch (Throwable $e) {
  error_log('Server error: ' . $e->getMessage());
  out(500, ['ok' => false, 'message' => 'Server error']);
}
