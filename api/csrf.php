<?php
// api/csrf.php
declare(strict_types=1);

function csrf_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Secure defaults (adjust path/domain if needed)
        ini_set('session.cookie_httponly', '1');
        // If you use HTTPS in production, set this to 1
        // ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_samesite', 'Strict');
        session_start();
    }
}

function csrf_token(): string
{
    csrf_session_start();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        // 32 bytes -> 64 hex chars
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    csrf_session_start();

    if (!is_string($token) || $token === '') return false;
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) return false;

    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_require_or_403(callable $respondJson = null): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;

    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

    if (!csrf_verify(is_string($token) ? $token : null)) {
        if ($respondJson) {
            $respondJson(403, ['error' => 'CSRF validation failed']);
        }
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'CSRF validation failed']);
        exit;
    }
}
