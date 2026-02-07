<?php
// api/logout.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$_SESSION = [];


if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}


session_destroy();


header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");


http_response_code(200);
echo json_encode([
    "status" => "success",
    "message" => "Logged out successfully"
]);
exit;
