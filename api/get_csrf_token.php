<?php
// api/get_csrf_token.php
declare(strict_types=1);

require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=utf-8');

$token = csrf_token();

echo json_encode(['csrf_token' => $token]);
exit;